<?php

namespace App\Modules\SaasCore\Services;

use App\Exceptions\CheckoutNotAllowedException;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Providers\PaypalPaymentProvider;
use App\Services\BaseService;

/**
 * Guards + starts a PayPal subscription for a plan. Returns the approval URL
 * the browser must be redirected to; throws CheckoutNotAllowedException
 * (translated, user-displayable message) when the tenant/plan state forbids
 * checkout. Mirrors StripeCheckoutService exactly, but depends on the
 * CONCRETE PaypalPaymentProvider rather than PaymentProviderInterface — the
 * interface is still flat-bound to Stripe (AppServiceProvider), so a second
 * gateway resolves by depending on its own class directly, same as how
 * ActivateStripeSubscriptionAction's webhook sibling already does for Stripe.
 */
class PaypalCheckoutService extends BaseService
{
    public function __construct(
        private readonly PaypalPaymentProvider $provider,
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

        if ($plan->paypalPlanIdFor($billingCycle) === null) {
            throw new CheckoutNotAllowedException(__('common.billing.plan_not_synced'));
        }

        // Already paying for this exact plan + cycle through PayPal (and not
        // winding down) — nothing to buy.
        $current = $tenant->activeSubscription;

        if ($current !== null
            && $current->gateway === Subscription::GATEWAY_PAYPAL
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
