<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\BaseService;

/**
 * The single source of truth for a tenant's settings: every default lives
 * here, and every write goes through update() so the change is AUDITED.
 *
 * Before this, settings were read with scattered `$tenant->settings['x'] ?? y`
 * defaults that could (and did) drift between readers. Read through get() /
 * all() instead.
 */
class TenantSettingsService extends BaseService
{
    /** Every known setting => its default. */
    public const DEFAULTS = [
        // Company profile
        'legal_name' => null,
        'abn' => null,
        'phone' => null,
        'email' => null,
        'website' => null,
        'address' => null,
        'logo_path' => null,
        'brand_colour' => '#0f172a',
        'default_state' => null,

        // Regional
        'timezone' => 'Australia/Sydney',
        'currency' => 'AUD',
        'date_format' => 'd/m/Y',

        // Notifications — providers + channels
        'email_provider' => 'log',
        'sms_provider' => 'log',
        'notify_email_enabled' => true,
        'notify_sms_enabled' => false,
        'notify_whatsapp_enabled' => false,

        // Fleet reminders
        'fleet_reminders_enabled' => true,
        'fleet_reminder_days' => Vehicle::DEFAULT_REMINDER_DAYS,
        'fleet_reminder_km' => Vehicle::DEFAULT_REMINDER_KM,

        // Late fees (read by ApplyLateFeeAction; previously unsettable)
        'late_fees_enabled' => true,
        'late_fee_grace_days' => 3, // matches LateFeeService's long-standing default
        'late_fee_type' => 'fixed',
        'late_fee_amount' => 5000, // cents
        'late_fee_percentage' => 0,

        // Invoicing
        'invoice_prefix' => 'INV-',
        'invoice_payment_terms_days' => 7,
        'invoice_footer_note' => null,
        // How invoices LOOK — layout, logo, colour, wording (see
        // InvoiceTemplateService::DEFAULTS). Empty = the shipped default.
        'invoice_template' => [],
        // Australian tax invoices must show the ABN and the GST. Rental prices
        // are GST-INCLUSIVE, so GST is 1/11 of the total — no stored amount
        // changes either way, only what the PDF states.
        'gst_registered' => true,

        // Integrations
        'ai_provider' => 'log',

        // Public lead form
        'lead_form_enabled' => true,
        'lead_form_intro' => '',
        'lead_form_allowed_domains' => [],

        // Per-trigger notification matrix (empty = the built-in defaults)
        'notification_matrix' => [],
    ];

    /** Australian timezones offered in the regional settings. */
    public const TIMEZONES = [
        'Australia/Sydney', 'Australia/Melbourne', 'Australia/Brisbane',
        'Australia/Adelaide', 'Australia/Perth', 'Australia/Hobart',
        'Australia/Darwin', 'Australia/Canberra',
    ];

    public const DATE_FORMATS = ['d/m/Y', 'd M Y', 'Y-m-d'];

    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Every setting for a tenant, defaults filled in.
     *
     * @return array<string, mixed>
     */
    public function all(Tenant $tenant): array
    {
        return [...self::DEFAULTS, ...($tenant->settings ?? [])];
    }

    public function get(Tenant $tenant, string $key): mixed
    {
        return $this->all($tenant)[$key] ?? null;
    }

    /**
     * Merge changes in, keeping unrelated keys, and write ONE audit entry
     * naming the section that changed.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(Tenant $tenant, array $changes, string $section): Tenant
    {
        $before = $this->all($tenant);

        $tenant->settings = [...($tenant->settings ?? []), ...$changes];
        $tenant->save();

        $this->audit->log(
            action: "settings.{$section}.updated",
            subjectType: AuditLog::SUBJECT_SETTINGS,
            subjectId: $tenant->id,
            subjectLabel: $section,
            old: array_intersect_key($before, $changes),
            new: $changes,
            tenant: $tenant,
        );

        return $tenant;
    }

    /** The tenant's timezone, used for display, PDFs and scheduling. */
    public function timezone(?Tenant $tenant = null): string
    {
        $tenant ??= app()->bound('current_tenant') ? app('current_tenant') : null;

        $timezone = $tenant === null ? null : ($tenant->settings['timezone'] ?? null);

        return in_array($timezone, self::TIMEZONES, true) ? $timezone : self::DEFAULTS['timezone'];
    }
}
