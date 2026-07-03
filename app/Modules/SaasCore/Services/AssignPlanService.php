<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Assigns a plan to a tenant, replacing any current subscription — the ONLY
 * sanctioned path to change a tenant's plan. Called from
 * TenantManagementController@assignPlan, the super-admin
 * UpgradeRequestController@complete (manual assignment), and
 * ActivateStripeSubscriptionAction (Stripe checkout, which passes the gateway
 * attributes so the new subscription row carries its Stripe identity).
 *
 * Runs with NO bound tenant (super admin context), so:
 *   - the tenant's existing subscriptions are read via $tenant->subscriptions()
 *     (which drops TenantScope), and
 *   - the new Subscription is created with tenant_id passed EXPLICITLY (the
 *     INSERT skips TenantScope — same pattern as TenantOnboardingService).
 *
 * Everything happens in ONE DB::transaction so a tenant can never be left with
 * two active subscriptions or a plan_id that disagrees with its subscription.
 */
class AssignPlanService extends BaseService
{
    /**
     * @param  string  $billingCycle  Subscription::BILLING_MONTHLY|BILLING_ANNUAL
     * @param  array<string, mixed>  $gatewayAttributes  Extra columns for the new
     *         subscription row (gateway / gateway_subscription_id /
     *         stripe_price_id / stripe_status). Empty for manual assignment.
     */
    public function execute(Tenant $tenant, int $planId, string $billingCycle, array $gatewayAttributes = []): Subscription
    {
        return DB::transaction(function () use ($tenant, $planId, $billingCycle, $gatewayAttributes) {
            $now = now();

            // Cancel every currently-granting subscription (active OR trialing).
            $tenant->subscriptions()
                ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
                ->get()
                ->each(function (Subscription $sub) use ($now) {
                    $sub->update([
                        'status' => Subscription::STATUS_CANCELLED,
                        'cancelled_at' => $now,
                    ]);
                });

            $periodEnd = $billingCycle === Subscription::BILLING_ANNUAL
                ? $now->copy()->addYear()
                : $now->copy()->addMonthNoOverflow();

            // tenant_id passed explicitly — no bound tenant in this context.
            $subscription = Subscription::create(array_merge([
                'tenant_id' => $tenant->id,
                'plan_id' => $planId,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_cycle' => $billingCycle,
                'current_period_start' => $now,
                'current_period_end' => $periodEnd,
                'trial_ends_at' => null,
            ], $gatewayAttributes));

            // Assigning a paid plan promotes the tenant to active and ends any
            // trial — leaving a "trial" status against a paid subscription is a
            // data-integrity bug. Same transaction.
            $tenant->update([
                'plan_id' => $planId,
                'status' => Tenant::STATUS_ACTIVE,
                'trial_ends_at' => null,
            ]);

            SubscriptionUpgraded::dispatch($tenant, $subscription);

            return $subscription;
        });
    }
}
