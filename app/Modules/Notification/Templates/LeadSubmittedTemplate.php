<?php

namespace App\Modules\Notification\Templates;

use App\Modules\CRM\Models\Lead;

/**
 * "New lead submitted" — sent to the tenant admin (NOT the customer) when a
 * prospect completes the public intake form. Pure: builds content from the
 * lead.
 */
class LeadSubmittedTemplate
{
    use FormatsNotifications;

    public function build(Lead $lead): NotificationContent
    {
        $name = $lead->name ?: 'A new lead';
        $phone = $lead->phone ?: '—';
        $email = $lead->email ?: '—';
        $when = $this->date($lead->submitted_at);

        $subject = 'New lead submitted';

        $emailBody = $this->emailHtml($subject, [
            "{$name} has submitted an intake form.",
            "Phone: {$phone}",
            "Email: {$email}",
            "Submitted: {$when}",
            'Open the Leads section to review and convert this lead.',
        ]);

        $sms = "New lead: {$name} ({$phone}) submitted an intake form. Review it in your DVARO Leads section.";

        return new NotificationContent($subject, $emailBody, $sms);
    }
}
