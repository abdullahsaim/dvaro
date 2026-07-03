<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Templates\SubscriptionPaymentFailedTemplate;
use App\Modules\SaasCore\Events\SubscriptionPaymentFailed;

/**
 * Notifies the tenant admin that a subscription payment failed (past_due).
 * Queued; admin recipients are email-only.
 */
class SendSubscriptionPaymentFailedNotification extends QueuedNotificationListener
{
    public function handle(SubscriptionPaymentFailed $event): void
    {
        $tenant = $this->bindTenant((int) $event->tenant->id);
        if ($tenant === null) {
            return;
        }

        try {
            $event->subscription->loadMissing('plan');

            $content = (new SubscriptionPaymentFailedTemplate())->build($event->subscription);
            $this->notifyAdmin($tenant, $content, 'subscription.payment_failed');
        } finally {
            $this->forgetTenant();
        }
    }
}
