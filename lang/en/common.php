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

    'mechanic' => [
        'created' => 'Mechanic added.',
        'updated' => 'Mechanic updated.',
        'deleted' => 'Mechanic removed.',
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

    'profile' => [
        'updated'          => 'Profile updated.',
        'password_updated' => 'Password updated.',
        'pin_updated'      => 'PIN updated.',
    ],

    'ai' => [
        'conversation_deleted' => 'Conversation deleted.',
    ],

    'reporting' => [
        'export_queued' => 'Export queued — it will be ready to download shortly.',
    ],

    'cms' => [
        'content_updated'        => 'Content updated.',
        'image_updated'          => 'Image updated.',
        'demo_requested'         => 'Thanks — we will be in touch shortly.',
        'contact_sent'           => 'Thanks for getting in touch. We will reply soon.',
        'demo_marked_contacted'  => 'Request marked as contacted.',
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
        'plan_assigned'           => 'Plan assigned to tenant.',
        'payment_recorded'        => 'Offline payment recorded.',
        'no_active_subscription'  => 'This tenant has no active subscription to record a payment against.',
        'upgrade_marked_contacted' => 'Upgrade request marked as contacted.',
        'upgrade_completed'       => 'Upgrade completed and plan assigned.',
    ],

    'billing' => [
        'upgrade_requested' => 'Request received — our team will contact you shortly.',
        // Stripe checkout / cancellation flash + guard messages.
        'checkout_failed'    => 'We could not start the checkout. Please try again shortly.',
        'checkout_cancelled' => 'Checkout cancelled — you have not been charged.',
        'plan_unavailable'   => 'This plan is not currently available.',
        'plan_is_free'       => 'This plan is free and does not require payment.',
        'plan_not_synced'    => 'Online payment for this plan is not available yet — please send an upgrade request instead.',
        'already_subscribed' => 'You are already subscribed to this plan.',
        'no_stripe_subscription' => 'There is no active online subscription to cancel.',
        'cancel_failed'      => 'We could not cancel the subscription. Please try again shortly.',
        'cancel_requested'   => 'Cancellation scheduled — your plan remains active until the end of the current period.',
    ],
];
