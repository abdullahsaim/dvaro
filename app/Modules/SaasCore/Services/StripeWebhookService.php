<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\Actions\ActivateStripeSubscriptionAction;
use App\Modules\SaasCore\Events\SubscriptionCancelled;
use App\Modules\SaasCore\Events\SubscriptionPaymentFailed;
use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;

/**
 * Processes VERIFIED Stripe webhook events (StripeWebhookController checks the
 * signature before anything reaches this service — never call it with an
 * unverified payload).
 *
 * Runs in webhook context with NO bound tenant: every Subscription read drops
 * TenantScope and tenants are resolved explicitly (by stripe_customer_id /
 * client_reference_id). Unknown event types and unmatched subscriptions are
 * logged and ignored — Stripe fires many event types we don't consume, and an
 * `updated` for a subscription we never activated is not an error.
 */
class StripeWebhookService extends BaseService
{
    public function __construct(
        private readonly ActivateStripeSubscriptionAction $activate,
    ) {}

    public function handle(object $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->checkoutCompleted($event->data->object),
            'customer.subscription.updated' => $this->subscriptionUpdated($event->data->object),
            'customer.subscription.deleted' => $this->subscriptionDeleted($event->data->object),
            'invoice.payment_failed' => $this->paymentFailed($event->data->object),
            default => null, // not a consumed event type — acknowledged, ignored
        };
    }

    /**
     * A paid checkout — the ONLY place a Stripe subscription is activated
     * locally (never the success-URL redirect). The plan + cycle ride in the
     * session metadata we set at session creation; the Stripe price is derived
     * from the plan record. ActivateStripeSubscriptionAction is idempotent
     * across Stripe's webhook retries.
     */
    private function checkoutCompleted(object $session): void
    {
        if (($session->mode ?? null) !== 'subscription') {
            return;
        }

        $tenant = $this->resolveTenant($session);

        if ($tenant === null) {
            Log::error('Stripe webhook: checkout completed for unknown tenant', [
                'session_id' => $session->id ?? null,
                'customer' => $session->customer ?? null,
                'client_reference_id' => $session->client_reference_id ?? null,
            ]);

            return;
        }

        $plan = Plan::find((int) ($session->metadata->plan_id ?? 0));
        $billingCycle = (string) ($session->metadata->billing_cycle ?? '');

        if ($plan === null || ! in_array($billingCycle, [Subscription::BILLING_MONTHLY, Subscription::BILLING_ANNUAL], true)) {
            Log::error('Stripe webhook: checkout session missing/invalid plan metadata', [
                'session_id' => $session->id ?? null,
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        $priceId = $plan->stripePriceIdFor($billingCycle) ?? '';

        $this->activate->execute(
            (string) $session->id,
            (string) $session->subscription,
            $priceId,
            $tenant,
        );
    }

    /**
     * Keep stripe_status current. A subscription flagged cancel_at_period_end
     * reads as 'canceling' (Stripe's own status stays 'active' until the
     * period lapses). past_due also demotes the LOCAL status — and fires
     * SubscriptionPaymentFailed only on the transition INTO past_due, so the
     * admin isn't re-notified on every Stripe retry; recovery back to active
     * restores the local status.
     */
    private function subscriptionUpdated(object $stripeSub): void
    {
        $subscription = $this->findByGatewayId((string) $stripeSub->id);

        if ($subscription === null) {
            return;
        }

        $stripeStatus = (string) $stripeSub->status;
        $wasPastDue = $subscription->stripe_status === 'past_due';

        $updates = [
            'stripe_status' => ($stripeSub->cancel_at_period_end ?? false) && $stripeStatus === 'active'
                ? Subscription::STRIPE_STATUS_CANCELING
                : $stripeStatus,
        ];

        if ($stripeStatus === 'past_due') {
            $updates['status'] = Subscription::STATUS_PAST_DUE;
        } elseif ($stripeStatus === 'active' && $subscription->status === Subscription::STATUS_PAST_DUE) {
            $updates['status'] = Subscription::STATUS_ACTIVE;
        }

        // Plan change reconciliation: a self-service upgrade (UpgradeSubscriptionAction)
        // already updated plan_id/stripe_price_id synchronously for an immediate
        // UI reflection — this just confirms it (idempotent) and is also the
        // ONLY sync path for a plan changed directly in the Stripe dashboard.
        $newPriceId = $stripeSub->items->data[0]->price->id ?? null;
        if (is_string($newPriceId) && $newPriceId !== '' && $newPriceId !== $subscription->stripe_price_id) {
            $newPlan = Plan::query()
                ->where('stripe_monthly_price_id', $newPriceId)
                ->orWhere('stripe_annual_price_id', $newPriceId)
                ->first();

            if ($newPlan !== null) {
                $updates['plan_id'] = $newPlan->id;
                $updates['stripe_price_id'] = $newPriceId;
            }
        }

        if (isset($stripeSub->current_period_start, $stripeSub->current_period_end)) {
            $updates['current_period_start'] = now()->setTimestamp((int) $stripeSub->current_period_start);
            $updates['current_period_end'] = now()->setTimestamp((int) $stripeSub->current_period_end);
        }

        // Captured BEFORE update() — Eloquent refreshes "original" to the new
        // values as soon as save() runs, so this must be read first.
        $previousPlanId = $subscription->plan_id;

        $subscription->update($updates);

        if (isset($updates['plan_id']) && $updates['plan_id'] !== $previousPlanId) {
            $subscription->tenant->update(['plan_id' => $updates['plan_id']]);
            SubscriptionUpgraded::dispatch($subscription->tenant, $subscription);
        }

        if ($stripeStatus === 'past_due' && ! $wasPastDue) {
            SubscriptionPaymentFailed::dispatch($subscription->tenant, $subscription);
        }
    }

    /**
     * The gateway subscription is gone for good (period lapsed after a cancel,
     * or Stripe abandoned collection). Cancel locally; the tenant reverts to
     * trial when still inside a trial window, else cancelled.
     */
    private function subscriptionDeleted(object $stripeSub): void
    {
        $subscription = $this->findByGatewayId((string) $stripeSub->id);

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'stripe_status' => (string) ($stripeSub->status ?? 'canceled'),
            'cancelled_at' => now(),
        ]);

        /** @var Tenant $tenant */
        $tenant = $subscription->tenant;

        $tenant->update([
            'status' => $tenant->trial_ends_at?->isFuture()
                ? Tenant::STATUS_TRIAL
                : Tenant::STATUS_CANCELLED,
        ]);

        SubscriptionCancelled::dispatch($tenant, $subscription);
    }

    /**
     * A subscription invoice failed to collect. Same transition-only
     * notification guard as subscriptionUpdated — Stripe usually sends both
     * events for one failure, and the admin should hear about it once.
     */
    private function paymentFailed(object $stripeInvoice): void
    {
        $gatewaySubId = $stripeInvoice->subscription ?? null;

        if (! is_string($gatewaySubId) || $gatewaySubId === '') {
            return; // not a subscription invoice
        }

        $subscription = $this->findByGatewayId($gatewaySubId);

        if ($subscription === null) {
            return;
        }

        $wasPastDue = $subscription->stripe_status === 'past_due';

        $subscription->update([
            'stripe_status' => 'past_due',
            'status' => Subscription::STATUS_PAST_DUE,
        ]);

        if (! $wasPastDue) {
            SubscriptionPaymentFailed::dispatch($subscription->tenant, $subscription);
        }
    }

    /**
     * The tenant behind a checkout session — by stripe_customer_id first, then
     * the client_reference_id fallback set at session creation.
     */
    private function resolveTenant(object $session): ?Tenant
    {
        $customerId = $session->customer ?? null;

        if (is_string($customerId) && $customerId !== '') {
            $tenant = Tenant::query()->where('stripe_customer_id', $customerId)->first();

            if ($tenant !== null) {
                return $tenant;
            }
        }

        $reference = $session->client_reference_id ?? null;

        return filled($reference) ? Tenant::find((int) $reference) : null;
    }

    private function findByGatewayId(string $gatewaySubscriptionId): ?Subscription
    {
        $subscription = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', $gatewaySubscriptionId)
            ->first();

        if ($subscription === null) {
            Log::info('Stripe webhook: no local subscription for gateway id', [
                'gateway_subscription_id' => $gatewaySubscriptionId,
            ]);
        }

        return $subscription;
    }
}
