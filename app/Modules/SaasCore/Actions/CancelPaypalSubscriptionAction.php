<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Exceptions\CheckoutNotAllowedException;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Providers\PaypalPaymentProvider;

/**
 * Asks PayPal to cancel the tenant's subscription and flags it 'canceling'
 * locally. Mirrors CancelStripeSubscriptionAction, but PayPal's cancel call
 * is IMMEDIATE on PayPal's side (no "at period end" option) — the definitive
 * local cancellation still only happens on the BILLING.SUBSCRIPTION.CANCELLED
 * webhook, keeping one sanctioned cancellation path for both gateways.
 *
 * Throws CheckoutNotAllowedException (translated message) when there is no
 * cancellable PayPal subscription.
 */
class CancelPaypalSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly PaypalPaymentProvider $provider,
    ) {}

    public function execute(Tenant $tenant): Subscription
    {
        $subscription = $tenant->activeSubscription;

        if ($subscription === null
            || $subscription->gateway !== Subscription::GATEWAY_PAYPAL
            || blank($subscription->gateway_subscription_id)
        ) {
            throw new CheckoutNotAllowedException(__('common.billing.no_stripe_subscription'));
        }

        if ($subscription->stripe_status === Subscription::STRIPE_STATUS_CANCELING) {
            return $subscription; // idempotent — already winding down
        }

        $this->provider->cancelSubscription((string) $subscription->gateway_subscription_id);

        $subscription->update(['stripe_status' => Subscription::STRIPE_STATUS_CANCELING]);

        return $subscription;
    }
}
