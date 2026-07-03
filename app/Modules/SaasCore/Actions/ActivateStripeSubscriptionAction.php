<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\AssignPlanService;
use App\Scopes\TenantScope;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Activates a tenant's subscription after a PAID Stripe Checkout — called ONLY
 * from the checkout.session.completed webhook (never from the success-URL
 * redirect, which anyone can visit without paying).
 *
 * Runs in webhook context with NO bound tenant: every read drops TenantScope
 * and every write passes tenant_id explicitly (same pattern as
 * AssignPlanService, which this action delegates to — keeping a single
 * sanctioned plan-assignment path; SubscriptionUpgraded fires in there).
 *
 * IDEMPOTENT: Stripe retries webhooks, so a subscription that already exists
 * for this Stripe subscription id is returned as-is — no double activation,
 * no duplicate payment record.
 */
class ActivateStripeSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly AssignPlanService $assignPlan,
    ) {}

    public function execute(
        string $stripeSessionId,
        string $stripeSubscriptionId,
        string $stripePriceId,
        Tenant $tenant,
    ): Subscription {
        // Idempotency guard — this webhook already activated the subscription.
        $existing = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', $stripeSubscriptionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        [$plan, $billingCycle] = $this->resolvePlan($stripePriceId);

        return DB::transaction(function () use ($stripeSessionId, $stripeSubscriptionId, $stripePriceId, $tenant, $plan, $billingCycle) {
            $subscription = $this->assignPlan->execute($tenant, $plan->id, $billingCycle, [
                'gateway' => Subscription::GATEWAY_STRIPE,
                'gateway_subscription_id' => $stripeSubscriptionId,
                'stripe_price_id' => $stripePriceId,
                'stripe_status' => 'active',
            ]);

            // The checkout payment itself — same ledger the super admin's
            // offline records live in. recorded_by stays null (system/webhook).
            SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->priceFor($billingCycle),
                'currency' => 'AUD',
                'method' => SubscriptionPayment::METHOD_STRIPE,
                'reference' => $stripeSessionId,
                'paid_at' => now(),
            ]);

            return $subscription;
        });
    }

    /**
     * Match the purchased Stripe Price back to a DVARO plan + billing cycle.
     *
     * @return array{0: Plan, 1: string}
     */
    private function resolvePlan(string $stripePriceId): array
    {
        $plan = Plan::query()
            ->where('stripe_monthly_price_id', $stripePriceId)
            ->orWhere('stripe_annual_price_id', $stripePriceId)
            ->first();

        if ($plan === null) {
            throw new RuntimeException(
                "No plan matches Stripe price [{$stripePriceId}] — was stripe:sync-plans run before checkout?"
            );
        }

        $billingCycle = $plan->stripe_annual_price_id === $stripePriceId
            ? Subscription::BILLING_ANNUAL
            : Subscription::BILLING_MONTHLY;

        return [$plan, $billingCycle];
    }
}
