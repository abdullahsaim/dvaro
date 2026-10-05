<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\Actions\ActivatePaypalSubscriptionAction;
use App\Modules\SaasCore\Events\SubscriptionCancelled;
use App\Modules\SaasCore\Events\SubscriptionPaymentFailed;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\BaseService;
use Illuminate\Support\Facades\Log;

/**
 * Processes VERIFIED PayPal webhook events (PaypalWebhookController checks
 * the signature before anything reaches this service — never call it with an
 * unverified payload). Mirrors StripeWebhookService exactly.
 *
 * Runs in webhook context with NO bound tenant: every Subscription read drops
 * TenantScope and tenants are resolved explicitly (by custom_id, the tenant
 * id PayPal was told to carry at subscription creation). Unknown event types
 * and unmatched subscriptions are logged and ignored.
 */
class PaypalWebhookService extends BaseService
{
    public function __construct(
        private readonly ActivatePaypalSubscriptionAction $activate,
    ) {}

    public function handle(object $event): void
    {
        match ($event->event_type ?? null) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->subscriptionActivated($event->resource),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->subscriptionCancelled($event->resource),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->paymentFailed($event->resource),
            default => null, // not a consumed event type — acknowledged, ignored
        };
    }

    /**
     * A subscription the customer approved on PayPal's side — the ONLY place
     * a PayPal subscription is activated locally (never the return-URL
     * redirect). custom_id is the tenant id set at subscription creation.
     */
    private function subscriptionActivated(object $resource): void
    {
        $tenantId = (int) ($resource->custom_id ?? 0);
        $tenant = $tenantId > 0 ? Tenant::find($tenantId) : null;

        if ($tenant === null) {
            Log::error('PayPal webhook: subscription activated for unknown tenant', [
                'subscription_id' => $resource->id ?? null,
                'custom_id' => $resource->custom_id ?? null,
            ]);

            return;
        }

        $planId = (string) ($resource->plan_id ?? '');

        if ($planId === '') {
            Log::error('PayPal webhook: subscription activated with no plan_id', [
                'subscription_id' => $resource->id ?? null,
                'tenant_id' => $tenant->id,
            ]);

            return;
        }

        $this->activate->execute((string) $resource->id, $planId, $tenant);
    }

    /**
     * The gateway subscription is gone for good. Cancel locally; the tenant
     * reverts to trial when still inside a trial window, else cancelled.
     */
    private function subscriptionCancelled(object $resource): void
    {
        $subscription = $this->findByGatewayId((string) ($resource->id ?? ''));

        if ($subscription === null) {
            return;
        }

        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'stripe_status' => 'canceled',
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
     * A subscription payment failed to collect. Same transition-only
     * notification guard as StripeWebhookService::paymentFailed — the admin
     * should hear about it once.
     */
    private function paymentFailed(object $resource): void
    {
        $subscription = $this->findByGatewayId((string) ($resource->id ?? ''));

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

    private function findByGatewayId(string $gatewaySubscriptionId): ?Subscription
    {
        if ($gatewaySubscriptionId === '') {
            return null;
        }

        $subscription = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', $gatewaySubscriptionId)
            ->first();

        if ($subscription === null) {
            Log::info('PayPal webhook: no local subscription for gateway id', [
                'gateway_subscription_id' => $gatewaySubscriptionId,
            ]);
        }

        return $subscription;
    }
}
