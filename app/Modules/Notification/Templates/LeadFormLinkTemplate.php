<?php

namespace App\Modules\Notification\Templates;

/**
 * "Tell us what you need" — the tenant's public lead-form link, sent to a
 * prospective customer by email or SMS (SendLeadFormLinkJob). Pure: no I/O.
 */
class LeadFormLinkTemplate
{
    use FormatsNotifications;

    public function build(string $tenantName, string $url): NotificationContent
    {
        $subject = "{$tenantName}: rental enquiry form";

        $email = $this->emailHtml($subject, [
            "Thanks for your interest in renting with {$tenantName}.",
            'Please fill in this short form and we will be in touch:',
            $url,
        ]);

        $sms = "{$tenantName}: please fill in our rental enquiry form and we'll be in touch: {$url}";

        return new NotificationContent($subject, $email, $sms);
    }
}
