<?php

namespace App\Modules\SaasCore\Services;

use App\Contracts\PaymentProviderInterface;
use App\Exceptions\CheckoutNotAllowedException;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;

/**
 * Guards + starts a Stripe Checkout for a plan. Returns the hosted checkout
 * URL the browser must be redirected to; throws CheckoutNotAllowedException
 * (translated, user-displayable message) when the tenant/plan state forbids
 * checkout. All Stripe traffic goes through the payment provider contract.
 *
 * Nothing is persisted here beyond the provider's lazy Stripe-Customer
 * creation — activation happens exclusively in the webhook
 * (checkout.session.completed → ActivateStripeSubscriptionAction).
 */
class StripeCheckoutService extends BaseService
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
    ) {}

    public function checkoutUrl(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        string $successUrl,
        string $cancelUrl,
    ): string {
        if (! $plan->is_active) {
            throw new CheckoutNotAllowedException(__('common.billing.plan_unavailable'));
        }

        if ($plan->is_free) {
            throw new CheckoutNotAllowedException(__('common.billing.plan_is_free'));
        }

        if ($plan->stripePriceIdFor($billingCycle) === null) {
            throw new CheckoutNotAllowedException(__('common.billing.plan_not_synced'));
        }

        // Already paying for this exact plan + cycle through Stripe (and not
        // winding down) — nothing to buy. Switching plan/cycle is allowed: the
        // webhook activation cancels the old subscription via AssignPlanService.
        $current = $tenant->activeSubscription;

        if ($current !== null
            && $current->gateway === Subscription::GATEWAY_STRIPE
            && $current->plan_id === $plan->id
            && $current->billing_cycle === $billingCycle
            && $current->stripe_status !== Subscription::STRIPE_STATUS_CANCELING
        ) {
            throw new CheckoutNotAllowedException(__('common.billing.already_subscribed'));
        }

        return $this->provider->createCheckoutSession(
            $tenant, $plan, $billingCycle, $successUrl, $cancelUrl,
        );
    }
}
