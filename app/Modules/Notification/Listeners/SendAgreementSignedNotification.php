<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementSigningService;
use App\Modules\Notification\Services\NotificationMatrix;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Templates\AgreementSignedTemplate;
use App\Services\PdfAvailability;
use Illuminate\Support\Facades\Storage;

/**
 * Notifies the customer that their agreement is signed, with a working link
 * to view/download the signed copy — and the signed PDF itself attached when
 * it's ready in time. Queued.
 *
 * GenerateAgreementPdfJob races this listener (both queue off AgreementSigned
 * independently), so the PDF may genuinely not exist yet when this runs —
 * that is NOT an error, just a link-only email like before this attachment
 * existed. The customer can always fetch the PDF from the portal link.
 */
class SendAgreementSignedNotification extends QueuedNotificationListener
{
    public function __construct(
        NotificationService $notifications,
        NotificationMatrix $matrix,
        private readonly AgreementSigningService $signing,
        private readonly PdfAvailability $pdfAvailability,
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
            $this->notifyCustomer($tenant, $customer, $content, 'agreement.signed', $this->pdfAttachment($agreement));
        } finally {
            $this->forgetTenant();
        }
    }

    /**
     * @return array<int, array{filename: string, content: string, mime: string}>|null
     */
    private function pdfAttachment(Agreement $agreement): ?array
    {
        if (! $this->pdfAvailability->exists($agreement->pdf_path)) {
            return null;
        }

        $content = Storage::disk(config('filesystems.default'))->get($agreement->pdf_path);

        if ($content === null) {
            return null;
        }

        return [[
            'filename' => 'agreement-'.$agreement->id.'.pdf',
            'content' => $content,
            'mime' => 'application/pdf',
        ]];
    }
}
