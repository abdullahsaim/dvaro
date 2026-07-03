<?php

namespace App\Modules\SaasCore\Events;

use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant's gateway subscription is definitively cancelled
 * (Stripe's customer.subscription.deleted — i.e. the paid period lapsed after
 * a cancel-at-period-end, or Stripe gave up on collection). CLAUDE.md-required
 * event.
 *
 * Listener: SendSubscriptionCancelledNotification (queued, admin email).
 */
class SubscriptionCancelled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Subscription $subscription,
    ) {}
}
