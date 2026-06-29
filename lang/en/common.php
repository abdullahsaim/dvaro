<?php

/*
|--------------------------------------------------------------------------
| Common Language Lines (English)
|--------------------------------------------------------------------------
|
| Backend translation strings resolved via the __() helper. English only in
| v1 — never hardcode display strings, always use translation keys so new
| languages can be added without code changes.
|
*/

return [
    'app_name' => 'DVARO',
    'tagline'  => 'Intelligent Fleet & Rental Operations',

    'auth' => [
        'failed'   => 'These credentials do not match our records.',
        'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    ],

    'register' => [
        'failed'   => 'We could not complete your registration. Please try again.',
        'throttle' => 'Too many registration attempts. Please try again in :minutes minutes.',
    ],

    'fleet' => [
        'created'        => 'Vehicle added to your fleet.',
        'updated'        => 'Vehicle updated.',
        'deleted'        => 'Vehicle removed from your fleet.',
        'status_changed' => 'Vehicle status updated.',
        'qr_generated'   => 'QR code generated.',
    ],

    'workshop' => [
        'log_created'    => 'Service log created. The vehicle is now in maintenance.',
        'status_changed' => 'Service log status updated.',
        'part_added'     => 'Part added to the service log.',
    ],

    'customer' => [
        'created'                 => 'Customer added.',
        'updated'                 => 'Customer updated.',
        'deleted'                 => 'Customer removed.',
        'blacklisted'             => 'Customer blacklisted.',
        'unblacklisted'           => 'Customer removed from the blacklist.',
        'portal_invited'          => 'Portal access sent.',
        'portal_already'          => 'This customer already has portal access.',
        'portal_payment_recorded' => 'Payment recorded. Thank you.',
        'portal_invite_used'      => 'This invitation has already been used. Please log in.',
        'portal_invite_expired'   => 'This invitation has expired. Please ask for a new one.',
    ],

    'crm' => [
        'created'   => 'Lead created. Share the intake link below.',
        'deleted'   => 'Lead removed.',
        'converted' => 'Lead converted to a customer.',
        'expired'   => 'Intake link expired.',
    ],

    'agreement' => [
        'created'         => 'Agreement created as a draft.',
        'signed'          => 'Agreement signed. The PDF is being generated.',
        'version_created' => 'New agreement version created — it needs to be signed.',
    ],

    'invoice' => [
        'payment_recorded' => 'Payment recorded.',
        'marked_overdue'   => 'Invoice marked as overdue.',
        'vehicle_changed'  => 'Vehicle changed. Two prorated invoices were raised and a new agreement version created for re-signing.',
    ],

    'notifications' => [
        'saved' => 'Notification settings saved.',
    ],

    'ai' => [
        'conversation_deleted' => 'Conversation deleted.',
    ],

    'superadmin' => [
        'tenant_suspended'        => 'Tenant suspended.',
        'tenant_activated'        => 'Tenant activated.',
        'no_admin_to_impersonate' => 'This tenant has no admin user to impersonate.',
        'impersonation_stopped'   => 'Stopped impersonating.',
        'plan_created'            => 'Plan created.',
        'plan_updated'            => 'Plan updated.',
        'plan_toggled'            => 'Plan availability updated.',
        'settings_saved'          => 'Platform settings saved.',
    ],
];
