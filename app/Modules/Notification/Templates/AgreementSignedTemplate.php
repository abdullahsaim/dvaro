<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Agreement\Models\Agreement;

/**
 * "Your rental agreement is signed" — sent to the customer after an agreement
 * is signed. Pure: builds content from the agreement, no I/O.
 */
class AgreementSignedTemplate
{
    use FormatsNotifications;

    /**
     * $url is the customer's own review-and-sign link — it still works after
     * signing (AgreementSigningService never invalidates it), now showing a
     * read-only "here's your copy" page with the PDF download once it's
     * ready, instead of the sign form. This is what makes the download a
     * real, working link rather than a bare instruction to "check the portal"
     * — most customers who sign remotely were never given portal access.
     */
    public function build(Agreement $agreement, string $url): NotificationContent
    {
        $customerName = $agreement->customer?->name ?? 'there';
        $vehicle = $this->vehicleLabel($agreement);
        $start = $this->date($agreement->start_date);

        $subject = 'Your rental agreement is signed';

        $email = $this->emailHtml($subject, [
            "Hi {$customerName},",
            "Thank you — your rental agreement (version {$agreement->version}) for {$vehicle} has been signed.",
            "Rental start date: {$start}.",
            'You can view and download your signed copy here:',
            $url,
            'Your first invoice will follow shortly.',
        ]);

        $sms = "Hi {$customerName}, your rental agreement for {$vehicle} is signed (start {$start}). View it here: {$url}";

        return new NotificationContent($subject, $email, $sms);
    }

    private function vehicleLabel(Agreement $agreement): string
    {
        $vehicle = $agreement->vehicle;

        if ($vehicle === null) {
            return 'your vehicle';
        }

        return trim("{$vehicle->make} {$vehicle->model} ({$vehicle->registration_number})");
    }
}
