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

    public function build(Agreement $agreement): NotificationContent
    {
        $customerName = $agreement->customer?->name ?? 'there';
        $vehicle = $this->vehicleLabel($agreement);
        $start = $this->date($agreement->start_date);

        $subject = 'Your rental agreement is signed';

        $email = $this->emailHtml($subject, [
            "Hi {$customerName},",
            "Thank you — your rental agreement (version {$agreement->version}) for {$vehicle} has been signed.",
            "Rental start date: {$start}.",
            'Your first invoice will follow shortly. You can view your agreement and invoices any time from your customer portal.',
        ]);

        $sms = "Hi {$customerName}, your rental agreement for {$vehicle} is signed (start {$start}). Your first invoice will follow shortly.";

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
