<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Contracts\PaymentProviderInterface;
use App\Exceptions\CheckoutNotAllowedException;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;

/**
 * Asks Stripe to cancel the tenant's subscription AT PERIOD END and flags it
 * 'canceling' locally. The tenant keeps access until the paid period lapses —
 * the definitive local cancellation (status=cancelled, tenant demotion,
 * SubscriptionCancelled event) happens when Stripe fires
 * customer.subscription.deleted, never here.
 *
 * Throws CheckoutNotAllowedException (translated message) when there is no
 * cancellable Stripe subscription.
 */
class CancelStripeSubscriptionAction extends BaseAction
{
    public function __construct(
        private readonly PaymentProviderInterface $provider,
    ) {}

    public function execute(Tenant $tenant): Subscription
    {
        $subscription = $tenant->activeSubscription;

        if ($subscription === null
            || $subscription->gateway !== Subscription::GATEWAY_STRIPE
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
