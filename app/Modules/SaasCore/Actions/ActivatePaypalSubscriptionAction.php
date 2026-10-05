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
 * Activates a tenant's subscription after PayPal confirms it — called ONLY
 * from the BILLING.SUBSCRIPTION.ACTIVATED webhook (never from the return-URL
 * redirect, which anyone can visit without actually approving on PayPal's
 * side). Mirrors ActivateStripeSubscriptionAction exactly.
 *
 * Runs in webhook context with NO bound tenant: every read drops TenantScope
 * and every write passes tenant_id explicitly, delegating the actual plan
 * assignment to AssignPlanService — the single sanctioned plan-assignment
 * path (SubscriptionUpgraded fires in there).
 *
 * IDEMPOTENT: PayPal retries webhooks, so a subscription that already exists
 * for this PayPal subscription id is returned as-is — no double activation,
 * no duplicate payment record.
 */
class ActivatePaypalSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly AssignPlanService $assignPlan,
    ) {}

    public function execute(
        string $paypalSubscriptionId,
        string $paypalPlanId,
        Tenant $tenant,
    ): Subscription {
        // Idempotency guard — this webhook already activated the subscription.
        $existing = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', $paypalSubscriptionId)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        [$plan, $billingCycle] = $this->resolvePlan($paypalPlanId);

        return DB::transaction(function () use ($paypalSubscriptionId, $tenant, $plan, $billingCycle) {
            $subscription = $this->assignPlan->execute($tenant, $plan->id, $billingCycle, [
                'gateway' => Subscription::GATEWAY_PAYPAL,
                'gateway_subscription_id' => $paypalSubscriptionId,
                'stripe_status' => 'active',
            ]);

            // The first subscription payment — same ledger the super admin's
            // offline records live in. recorded_by stays null (system/webhook).
            SubscriptionPayment::create([
                'tenant_id' => $tenant->id,
                'subscription_id' => $subscription->id,
                'amount' => $plan->priceFor($billingCycle),
                'currency' => 'AUD',
                'method' => SubscriptionPayment::METHOD_PAYPAL,
                'reference' => $paypalSubscriptionId,
                'paid_at' => now(),
            ]);

            return $subscription;
        });
    }

    /**
     * Match the subscribed PayPal billing Plan back to a DVARO plan + cycle.
     *
     * @return array{0: Plan, 1: string}
     */
    private function resolvePlan(string $paypalPlanId): array
    {
        $plan = Plan::query()
            ->where('paypal_monthly_plan_id', $paypalPlanId)
            ->orWhere('paypal_annual_plan_id', $paypalPlanId)
            ->first();

        if ($plan === null) {
            throw new RuntimeException(
                "No plan matches PayPal billing plan [{$paypalPlanId}] — was paypal:sync-plans run before checkout?"
            );
        }

        $billingCycle = $plan->paypal_annual_plan_id === $paypalPlanId
            ? Subscription::BILLING_ANNUAL
            : Subscription::BILLING_MONTHLY;

        return [$plan, $billingCycle];
    }
}
