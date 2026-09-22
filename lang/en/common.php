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
    'tagline' => 'Intelligent Fleet & Rental Operations',

    'auth' => [
        'failed' => 'These credentials do not match our records.',
        'throttle' => 'Too many login attempts. Please try again in :seconds seconds.',
    ],

    'register' => [
        'failed' => 'We could not complete your registration. Please try again.',
        'throttle' => 'Too many registration attempts. Please try again in :minutes minutes.',
    ],

    'fleet' => [
        'created' => 'Vehicle added to your fleet.',
        'updated' => 'Vehicle updated.',
        'deleted' => 'Vehicle removed from your fleet.',
        'status_changed' => 'Vehicle status updated.',
        'qr_generated' => 'QR code generated.',
        'odometer_recorded' => 'Odometer reading recorded.',
        'odometer_backwards' => 'Odometer readings can\'t go backwards. The current reading is :current km.',
        'odometer_negative' => 'The odometer reading must be zero or more.',
    ],

    'workshop' => [
        'log_created' => 'Service log created. The vehicle is now in maintenance.',
        'status_changed' => 'Service log status updated.',
        'part_added' => 'Part added to the service log.',
    ],

    'mechanic' => [
        'created' => 'Mechanic added.',
        'updated' => 'Mechanic updated.',
        'deleted' => 'Mechanic removed.',
    ],

    'customer' => [
        'created' => 'Customer added.',
        'updated' => 'Customer updated.',
        'deleted' => 'Customer removed.',
        'blacklisted' => 'Customer blacklisted.',
        'unblacklisted' => 'Customer removed from the blacklist.',
        'portal_invited' => 'Portal access sent.',
        'portal_already' => 'This customer already has portal access.',
        'portal_payment_recorded' => 'Payment recorded. Thank you.',
        'portal_invite_used' => 'This invitation has already been used. Please log in.',
        'portal_invite_expired' => 'This invitation has expired. Please ask for a new one.',
        'document_uploaded' => 'Document uploaded.',
    ],

    'plan' => [
        'file_too_large' => 'This file exceeds your plan\'s :limit MB upload limit. Upgrade your plan to upload larger files.',
    ],

    'crm' => [
        'created' => 'Lead created. Share the intake link below.',
        'deleted' => 'Lead removed.',
        'converted' => 'Lead converted to a customer.',
        'expired' => 'Intake link expired.',
        // Public lead form (share link / embed).
        'lead_form_iframe_title' => ':company rental enquiry form',
        'lead_form_start_date_attribute' => 'preferred start date',
        'lead_form_retry' => 'Something went wrong. Please check your details and try again.',
        'lead_form_captcha_failed' => 'Please tick "I\'m not a robot" and try again.',
        'lead_form_saved' => 'Lead form settings saved.',
        'lead_form_regenerated' => 'New link created. Update the embed code on your website.',
        'lead_form_link_sent' => 'The form link is on its way.',
        'lead_form_bad_domain' => '":domain" isn\'t a valid website address.',
    ],

    'agreement_template' => [
        'saved' => 'Agreement terms saved.',
        'copied' => 'Copied to your templates — edit the wording to suit your business.',
        'state_saved' => 'Default state saved.',
        'empty' => 'The terms can\'t be empty.',
        'unknown_field' => 'Unknown field ":field". Use the "Insert field" menu to add one that exists.',
        'not_copyable' => 'Only platform default templates can be copied.',
    ],

    'agreement' => [
        'created' => 'Agreement created as a draft.',
        'signed' => 'Agreement signed. The PDF is being generated.',
        'version_created' => 'New agreement version created — it needs to be signed.',
        'pdf_queued' => 'Generating the PDF — refresh in a moment.',
        'pdf_already_exists' => 'This agreement already has a PDF.',
    ],

    'invoice' => [
        'payment_recorded' => 'Payment recorded.',
        'marked_overdue' => 'Invoice marked as overdue.',
        'vehicle_changed' => 'Vehicle changed. Two prorated invoices were raised and a new agreement version created for re-signing.',
        'pdf_queued' => 'Rebuilding this invoice\'s PDF — it will refresh shortly.',
    ],

    'notifications' => [
        'saved' => 'Notification settings saved.',
    ],

    'settings' => [
        'company_saved' => 'Company profile saved.',
        'logo_saved' => 'Logo updated.',
        'logo_removed' => 'Logo removed.',
        'finance_saved' => 'Invoicing settings saved.',
        'regional_saved' => 'Regional settings saved.',
        'integrations_saved' => 'Integration settings saved.',
        'invoice_template_saved' => 'Invoice design saved.',
        'abn_format' => 'An ABN is 11 digits.',
        'colour_format' => 'Use a colour like #1a2b3c.',
    ],

    'staff' => [
        'invited' => 'Invitation sent.',
        'invite_resent' => 'Invitation sent again.',
        'invite_revoked' => 'Invitation revoked.',
        'updated' => 'Staff member updated.',
        'welcome' => 'Welcome aboard — your account is ready.',
        'email_taken' => 'Someone with that email already works here.',
        'already_invited' => 'That person already has a pending invitation.',
        'invite_invalid' => 'This invitation is no longer valid.',
        'cannot_change_own_role' => 'You can\'t change your own role. Ask another admin.',
        'cannot_deactivate_self' => 'You can\'t deactivate your own account.',
        'last_admin' => 'This is the only admin. Make someone else an admin first.',
    ],

    'audit' => [
        'system' => 'System',
    ],

    'expenses' => [
        'recorded' => 'Expense recorded.',
        'updated' => 'Expense updated.',
        'voided' => 'Expense voided.',
        'voided_locked' => 'A voided expense can\'t be edited.',
        'already_voided' => 'This expense is already voided.',
        'category_saved' => 'Category saved.',
        'category_exists' => 'You already have a category with that name.',
    ],

    'profile' => [
        'updated' => 'Profile updated.',
        'password_updated' => 'Password updated.',
        'pin_updated' => 'PIN updated.',
        'preferences_updated' => 'Preferences saved.',
    ],

    'ai' => [
        'conversation_deleted' => 'Conversation deleted.',
    ],

    'reporting' => [
        'export_queued' => 'Export queued — it will be ready to download shortly.',
    ],

    'cms' => [
        'content_updated' => 'Content updated.',
        'image_updated' => 'Image updated.',
        'demo_requested' => 'Thanks — we will be in touch shortly.',
        'contact_sent' => 'Thanks for getting in touch. We will reply soon.',
        'demo_marked_contacted' => 'Request marked as contacted.',
    ],

    'superadmin' => [
        'tenant_suspended' => 'Tenant suspended.',
        'tenant_activated' => 'Tenant activated.',
        'no_admin_to_impersonate' => 'This tenant has no admin user to impersonate.',
        'impersonation_stopped' => 'Stopped impersonating.',
        'plan_created' => 'Plan created.',
        'plan_updated' => 'Plan updated.',
        'plan_toggled' => 'Plan availability updated.',
        'settings_saved' => 'Platform settings saved.',
        'plan_assigned' => 'Plan assigned to tenant.',
        'payment_recorded' => 'Offline payment recorded.',
        'no_active_subscription' => 'This tenant has no active subscription to record a payment against.',
        'upgrade_marked_contacted' => 'Upgrade request marked as contacted.',
        'upgrade_completed' => 'Upgrade completed and plan assigned.',
    ],

    'billing' => [
        'upgrade_requested' => 'Request received — our team will contact you shortly.',
        // Stripe checkout / cancellation flash + guard messages.
        'checkout_failed' => 'We could not start the checkout. Please try again shortly.',
        'checkout_cancelled' => 'Checkout cancelled — you have not been charged.',
        'plan_unavailable' => 'This plan is not currently available.',
        'plan_is_free' => 'This plan is free and does not require payment.',
        'plan_not_synced' => 'Online payment for this plan is not available yet — please send an upgrade request instead.',
        'already_subscribed' => 'You are already subscribed to this plan.',
        'no_stripe_subscription' => 'There is no active online subscription to cancel.',
        'cancel_failed' => 'We could not cancel the subscription. Please try again shortly.',
        'cancel_requested' => 'Cancellation scheduled — your plan remains active until the end of the current period.',
    ],
];
