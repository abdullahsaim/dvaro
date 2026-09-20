<?php

/*
|--------------------------------------------------------------------------
| Notification trigger labels
|--------------------------------------------------------------------------
|
| One entry per row of the notification matrix (NotificationMatrix::TRIGGERS).
| Keys are the event type with dots replaced by underscores, because Laravel's
| translation keys are dot-paths: 'payment.received' => 'payment_received'.
|
| Displayed to tenant admins in Settings → Notifications. Never hardcode these
| in PHP — new languages must be able to translate them (CLAUDE.md, i18n).
|
*/

return [
    'triggers' => [
        'agreement_signed' => [
            'label' => 'Agreement signed',
            'description' => 'Sent to the customer with their signed rental agreement.',
        ],
        'invoice_generated' => [
            'label' => 'Invoice issued',
            'description' => 'Sent to the customer when an invoice is raised.',
        ],
        'invoice_late_fee' => [
            'label' => 'Late fee applied',
            'description' => 'Sent to the customer when an overdue invoice is charged a late fee.',
        ],
        'payment_received' => [
            'label' => 'Payment received',
            'description' => 'The customer\'s receipt.',
        ],
        'agreement_expiry' => [
            'label' => 'Agreement ending soon',
            'description' => 'A 14-day heads-up to your team about agreements about to end.',
        ],
        'fleet_reminder_digest' => [
            'label' => 'Fleet reminders',
            'description' => 'The daily digest of registration, insurance and service due dates.',
        ],
        'lead_submitted' => [
            'label' => 'New lead',
            'description' => 'Sent to your team when someone submits the lead form.',
        ],
        'subscription_payment_failed' => [
            'label' => 'Subscription payment failed',
            'description' => 'Billing alert for your own DVARO subscription.',
        ],
        'subscription_cancelled' => [
            'label' => 'Subscription cancelled',
            'description' => 'Confirmation that your own DVARO subscription was cancelled.',
        ],
    ],
];
