<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Agreement\Models\Agreement;

/**
 * "Please review and sign your rental agreement" — sent when staff choose to
 * send the agreement for remote signing, rather than (or alongside) signing
 * in person. Pure: builds content from the agreement + a pre-built URL, no I/O.
 */
class AgreementSigningLinkTemplate
{
    use FormatsNotifications;

    public function build(string $tenantName, Agreement $agreement, string $url): NotificationContent
    {
        $customerName = $agreement->customer?->name ?? 'there';
        $vehicle = $this->vehicleLabel($agreement);

        $subject = "Please review and sign your {$tenantName} rental agreement";

        $email = $this->emailHtml($subject, [
            "Hi {$customerName},",
            "{$tenantName} has sent you a rental agreement for {$vehicle} to review and sign.",
            'Open the link below to read the agreement and sign it online — no account or app required:',
            $url,
            'This link is unique to you — please do not forward it.',
        ]);

        $sms = "Hi {$customerName}, {$tenantName} has sent your rental agreement for {$vehicle} to review and sign: {$url}";

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
