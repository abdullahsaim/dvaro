<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\Agreement\Services\AgreementSigningService;
use App\Modules\Notification\Services\NotificationMatrix;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Templates\AgreementSignedTemplate;

/**
 * Notifies the customer that their agreement is signed, with a working link
 * to view/download the signed copy. Queued.
 */
class SendAgreementSignedNotification extends QueuedNotificationListener
{
    public function __construct(
        NotificationService $notifications,
        NotificationMatrix $matrix,
        private readonly AgreementSigningService $signing,
    ) {
        parent::__construct($notifications, $matrix);
    }

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

            // The link keeps working after signing (a read-only "here's your
            // copy" page), so this reuses whatever token exists — or issues
            // one now if the agreement was only ever signed in person.
            $url = $this->signing->publicUrl($agreement, $tenant->slug);

            $content = (new AgreementSignedTemplate)->build($agreement, $url);
            $this->notifyCustomer($tenant, $customer, $content, 'agreement.signed');
        } finally {
            $this->forgetTenant();
        }
    }
}
