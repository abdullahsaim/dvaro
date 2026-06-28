<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Customer\Models\Customer;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Templates\NotificationContent;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Base for every queued notification listener.
 *
 * All notification dispatch is queued (CLAUDE.md) — concrete listeners extend
 * this and implement handle(). They run in the queue worker with NO bound
 * tenant, so every listener MUST bindTenant() before traversing tenant-scoped
 * relations and forgetTenant() in a finally block (no leak to the next job).
 *
 * Event models restore via newQueryWithoutScopes (SerializesModels), so the
 * model itself deserializes fine without a bound tenant — but its relations do
 * not, hence the explicit binding.
 *
 * Channel rules (the confirmed defaults): email is the always-on channel (when
 * enabled + an address exists); SMS/WhatsApp fire only when the tenant enabled
 * them AND a phone number exists. Admin recipients (TenantUser) have no phone,
 * so ops notices are email-only.
 */
abstract class QueuedNotificationListener implements ShouldQueue
{
    use InteractsWithQueue;
    use SerializesModels;

    /** Dedicated queue for notification dispatch. */
    public string $queue = 'notifications';

    public function __construct(
        protected readonly NotificationService $notifications,
    ) {}

    /**
     * Bind the tenant for this listener's duration. Returns null (after logging)
     * when the tenant has vanished — the caller should then return.
     */
    protected function bindTenant(int $tenantId): ?Tenant
    {
        $tenant = Tenant::find($tenantId);

        if ($tenant === null) {
            Log::warning(static::class.': tenant not found', ['tenant_id' => $tenantId]);

            return null;
        }

        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    protected function forgetTenant(): void
    {
        app()->forgetInstance('current_tenant');
    }

    protected function emailEnabled(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['notify_email_enabled'] ?? true);
    }

    protected function smsEnabled(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['notify_sms_enabled'] ?? false);
    }

    protected function whatsappEnabled(Tenant $tenant): bool
    {
        return (bool) ($tenant->settings['notify_whatsapp_enabled'] ?? false);
    }

    /**
     * Send a customer-facing notification across every enabled channel.
     */
    protected function notifyCustomer(
        Tenant $tenant,
        Customer $customer,
        NotificationContent $content,
        string $eventType,
    ): void {
        $id = (int) $customer->id;
        $type = NotificationLog::TYPE_CUSTOMER;

        if ($this->emailEnabled($tenant) && filled($customer->email)) {
            $this->notifications->sendEmail(
                $tenant, $customer->email, $content->subject, $content->emailBody, $eventType, $id, $type,
            );
        }

        if ($this->smsEnabled($tenant) && filled($customer->phone)) {
            $this->notifications->sendSms(
                $tenant, $customer->phone, $content->smsBody, $eventType, $id, $type,
            );
        }

        if ($this->whatsappEnabled($tenant) && filled($customer->phone)) {
            $this->notifications->sendWhatsApp(
                $tenant, $customer->phone, $content->whatsappBody(), $eventType, $id, $type,
            );
        }
    }

    /**
     * Send an ops notification to the tenant admin (email-only — admins have no
     * phone field). No-op when email is disabled or no admin exists.
     */
    protected function notifyAdmin(
        Tenant $tenant,
        NotificationContent $content,
        string $eventType,
    ): void {
        if (! $this->emailEnabled($tenant)) {
            return;
        }

        // tenant is bound, so this resolves the bound tenant's admin only.
        $admin = TenantUser::where('role', TenantUser::ROLE_ADMIN)->first();

        if ($admin === null || blank($admin->email)) {
            Log::warning(static::class.': no admin recipient', ['tenant_id' => $tenant->id]);

            return;
        }

        $this->notifications->sendEmail(
            $tenant,
            $admin->email,
            $content->subject,
            $content->emailBody,
            $eventType,
            (int) $admin->id,
            NotificationLog::TYPE_TENANT_USER,
        );
    }
}
