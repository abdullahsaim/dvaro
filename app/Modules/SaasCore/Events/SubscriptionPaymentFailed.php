<?php

namespace App\Modules\SaasCore\Events;

use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired from the Stripe webhook when a subscription payment fails (the
 * subscription transitions INTO past_due, or an invoice.payment_failed
 * arrives). Fired only on the transition — never repeated while the
 * subscription stays past_due, so the admin is not spammed on every retry.
 *
 * Listener: SendSubscriptionPaymentFailedNotification (queued, admin email).
 */
class SubscriptionPaymentFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Subscription $subscription,
    ) {}
}
