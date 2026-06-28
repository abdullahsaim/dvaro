<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\Notification\Templates\AgreementSignedTemplate;

/**
 * Notifies the customer that their agreement is signed. Queued.
 */
class SendAgreementSignedNotification extends QueuedNotificationListener
{
    public function handle(AgreementSigned $event): void
    {
        $agreement = $event->agreement;

        $tenant = $this->bindTenant((int) $agreement->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            $agreement->loadMissing(['customer', 'vehicle']);
            $customer = $agreement->customer;

            if ($customer === null) {
                return;
            }

            $content = (new AgreementSignedTemplate())->build($agreement);
            $this->notifyCustomer($tenant, $customer, $content, 'agreement.signed');
        } finally {
            $this->forgetTenant();
        }
    }
}
