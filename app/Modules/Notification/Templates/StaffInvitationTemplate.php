<?php

namespace App\Modules\Notification\Templates;

/**
 * "You've been invited to join {company} on DVARO" — carries the set-password
 * link. Pure: no I/O. DVARO never emails a password.
 */
class StaffInvitationTemplate
{
    use FormatsNotifications;

    public function build(string $tenantName, string $inviteeName, string $url, mixed $expiresAt): NotificationContent
    {
        $subject = "You've been invited to {$tenantName} on DVARO";
        $expires = $this->date($expiresAt);

        $email = $this->emailHtml($subject, [
            "Hi {$inviteeName},",
            "{$tenantName} has invited you to their DVARO workspace.",
            'Choose your password here to get started:',
            $url,
            "This invitation expires on {$expires}. If you weren't expecting it, you can ignore this email.",
        ]);

        $sms = "{$tenantName} invited you to DVARO. Set your password: {$url} (expires {$expires})";

        return new NotificationContent($subject, $email, $sms);
    }
}
