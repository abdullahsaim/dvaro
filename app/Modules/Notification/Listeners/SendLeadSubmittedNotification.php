<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\CRM\Events\LeadSubmitted;
use App\Modules\Notification\Templates\LeadSubmittedTemplate;

/**
 * Notifies the tenant admin that a prospect submitted the public intake form.
 * Queued. Recipient is the tenant admin (email-only), not the customer.
 */
class SendLeadSubmittedNotification extends QueuedNotificationListener
{
    public function handle(LeadSubmitted $event): void
    {
        $lead = $event->lead;

        $tenant = $this->bindTenant((int) $lead->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            $content = (new LeadSubmittedTemplate())->build($lead);
            $this->notifyAdmin($tenant, $content, 'lead.submitted');
        } finally {
            $this->forgetTenant();
        }
    }
}
