<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Contracts\PaymentProviderInterface;
use App\Exceptions\UpgradeNotAllowedException;
use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use App\Services\PlatformActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Self-service, IN-PLACE plan change on an EXISTING gateway subscription —
 * deliberately NOT routed through AssignPlanService, whose cancel-and-recreate-
 * at-full-price design has no concept of proration. The gateway (Stripe today)
 * computes and charges the prorated difference for the rest of the current
 * period RIGHT NOW (PaymentProviderInterface::changeSubscriptionPlan), and
 * this action mirrors that result into the local row immediately for the
 * admin's own UI — the gateway's webhook (StripeWebhookService::subscriptionUpdated)
 * reconciles the same change again shortly after, harmlessly idempotent, and
 * is the only sync path for a plan changed directly in the gateway's dashboard.
 *
 * Eligibility: only a tenant with an ACTIVE subscription already on a paid
 * gateway can self-service upgrade here. A tenant with no gateway subscription
 * yet (on DVARO's own free trial, or manually assigned by a super admin) has
 * nothing to prorate against — they go through the existing Stripe Checkout
 * flow instead (a brand-new subscription, not a change to one).
 */
class UpgradeSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
        private readonly PlatformActivityLogger $activity,
    ) {}

    public function execute(Tenant $tenant, Plan $newPlan): Subscription
    {
        $subscription = $tenant->activeSubscription;

        if ($subscription === null || $subscription->gateway === null || $subscription->status !== Subscription::STATUS_ACTIVE) {
            throw new UpgradeNotAllowedException('This tenant has no active gateway subscription to change — use Stripe Checkout for a first-time subscription.');
        }

        if ((int) $subscription->plan_id === (int) $newPlan->id) {
            throw new UpgradeNotAllowedException('Already on this plan.');
        }

        $newPriceId = $subscription->billing_cycle === Subscription::BILLING_ANNUAL
            ? $newPlan->stripe_annual_price_id
            : $newPlan->stripe_monthly_price_id;

        if ($newPriceId === null) {
            throw new UpgradeNotAllowedException("Plan [{$newPlan->slug}] has no price for the {$subscription->billing_cycle} cycle — run stripe:sync-plans first.");
        }

        // The gateway call happens OUTSIDE the DB transaction — it is the
        // real-world, non-reversible event (the money actually moves); the
        // local row is kept in sync with whatever the gateway reports back,
        // never the other way around.
        $result = $this->provider->changeSubscriptionPlan((string) $subscription->gateway_subscription_id, $newPriceId);

        $previousPlanId = $subscription->plan_id;

        DB::transaction(function () use ($tenant, $newPlan, $subscription, $result) {
            $subscription->update([
                'plan_id' => $newPlan->id,
                'stripe_price_id' => $result['gateway_price_id'],
                'current_period_start' => $result['current_period_start'],
                'current_period_end' => $result['current_period_end'],
            ]);

            $tenant->update(['plan_id' => $newPlan->id]);

            if ($result['amount_charged'] > 0) {
                SubscriptionPayment::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $result['amount_charged'],
                    'currency' => 'AUD',
                    'method' => SubscriptionPayment::METHOD_STRIPE,
                    'notes' => "Prorated upgrade to {$newPlan->name}",
                    'paid_at' => now(),
                ]);
            }

            SubscriptionUpgraded::dispatch($tenant, $subscription);
        });

        $this->activity->log(
            'tenant.self_service_plan_upgrade',
            PlatformActivityLog::SUBJECT_SUBSCRIPTION,
            $subscription->id,
            $tenant->name,
            old: ['plan_id' => $previousPlanId],
            new: ['plan_id' => $newPlan->id, 'amount_charged' => $result['amount_charged']],
            tenant: $tenant,
        );

        return $subscription->fresh();
    }
}
