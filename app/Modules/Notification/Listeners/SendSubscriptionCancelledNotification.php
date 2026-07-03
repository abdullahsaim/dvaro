<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Templates\SubscriptionCancelledTemplate;
use App\Modules\SaasCore\Events\SubscriptionCancelled;

/**
 * Notifies the tenant admin that their gateway subscription was definitively
 * cancelled. Queued; admin recipients are email-only.
 */
class SendSubscriptionCancelledNotification extends QueuedNotificationListener
{
    public function handle(SubscriptionCancelled $event): void
    {
        $tenant = $this->bindTenant((int) $event->tenant->id);
        if ($tenant === null) {
            return;
        }

        try {
            $event->subscription->loadMissing('plan');

            $content = (new SubscriptionCancelledTemplate())->build($event->subscription);
            $this->notifyAdmin($tenant, $content, 'subscription.cancelled');
        } finally {
            $this->forgetTenant();
        }
    }
}
