<?php

namespace App\Modules\Notification\Templates;

/**
 * "Upcoming expiry" reminder — sent to the tenant admin 14 days before a date
 * lapses (vehicle registration, insurance, service due, or agreement end).
 *
 * Generic over the four kinds: ExpiryReminderService supplies a human label
 * ("registration for ABC123") and the expiry date. Pure: no I/O.
 */
class ExpiryReminderTemplate
{
    use FormatsNotifications;

    public function build(string $heading, string $subjectLabel, mixed $expiryDate): NotificationContent
    {
        $when = $this->date($expiryDate);

        $subject = "Reminder: {$heading}";

        $email = $this->emailHtml($subject, [
            "This is a 14-day reminder.",
            "{$subjectLabel} expires on {$when}.",
            'Please action this before it lapses.',
        ]);

        $sms = "DVARO reminder: {$subjectLabel} expires on {$when} (14 days). Please action it before it lapses.";

        return new NotificationContent($subject, $email, $sms);
    }
}
