<?php

namespace App\Modules\Notification\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Templates\ExpiryReminderTemplate;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends 14-day expiry reminders to each tenant's admin: vehicle registration,
 * insurance, and service due dates, plus agreement end dates.
 *
 * SINGLE WINDOW: exactly 14 days out (a precise date match), so each entity is
 * reminded once as the window passes — no 30/14/7 ladder.
 *
 * Scheduled daily. Runs with NO bound tenant (cron context), so it binds each
 * tenant before any tenant-scoped query — a bare query would otherwise throw
 * TenantNotResolvedException. NEVER throws / never aborts the batch: a per-tenant
 * try/catch logs and continues so one tenant can't block the platform.
 *
 * IDEMPOTENT: before each send it checks notification_logs for a matching
 * (notifiable, event_type) row created today, so a second run on the same day
 * never double-sends.
 */
class ExpiryReminderService extends BaseService
{
    private const WINDOW_DAYS = 14;

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function sweep(): void
    {
        $target = Carbon::today()->addDays(self::WINDOW_DAYS);

        foreach (Tenant::all() as $tenant) {
            app()->instance('current_tenant', $tenant);

            try {
                // Reminders are admin ops notices, gated by the tenant's email
                // toggle (admins have no phone — email-only).
                if (! (bool) ($tenant->settings['notify_email_enabled'] ?? true)) {
                    continue;
                }

                $admin = TenantUser::where('role', TenantUser::ROLE_ADMIN)->first();

                if ($admin === null || blank($admin->email)) {
                    continue;
                }

                $this->remindVehicles($tenant, $admin, $target);
                $this->remindAgreements($tenant, $admin, $target);
            } catch (Throwable $e) {
                Log::error('ExpiryReminderService: tenant sweep failed', [
                    'tenant_id' => $tenant->id,
                    'message' => $e->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('current_tenant');
            }
        }
    }

    private function remindVehicles(Tenant $tenant, TenantUser $admin, Carbon $target): void
    {
        $vehicles = Vehicle::query()
            ->where(function ($q) use ($target) {
                $q->whereDate('registration_expiry', $target)
                    ->orWhereDate('insurance_expiry', $target)
                    ->orWhereDate('next_service_due', $target);
            })
            ->get();

        foreach ($vehicles as $vehicle) {
            $reg = $vehicle->registration_number;

            if ($this->matches($vehicle->registration_expiry, $target)) {
                $this->send($tenant, $admin, NotificationLog::TYPE_VEHICLE, (int) $vehicle->id,
                    'vehicle.registration_expiry',
                    'Vehicle registration expiring', "Registration for {$reg}", $vehicle->registration_expiry);
            }

            if ($this->matches($vehicle->insurance_expiry, $target)) {
                $this->send($tenant, $admin, NotificationLog::TYPE_VEHICLE, (int) $vehicle->id,
                    'vehicle.insurance_expiry',
                    'Vehicle insurance expiring', "Insurance for {$reg}", $vehicle->insurance_expiry);
            }

            if ($this->matches($vehicle->next_service_due, $target)) {
                $this->send($tenant, $admin, NotificationLog::TYPE_VEHICLE, (int) $vehicle->id,
                    'vehicle.service_due',
                    'Vehicle service due', "Service for {$reg}", $vehicle->next_service_due);
            }
        }
    }

    private function remindAgreements(Tenant $tenant, TenantUser $admin, Carbon $target): void
    {
        $agreements = Agreement::query()
            ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
            ->whereDate('end_date', $target)
            ->with('customer')
            ->get();

        foreach ($agreements as $agreement) {
            $customer = $agreement->customer?->name ?? 'customer';

            $this->send($tenant, $admin, NotificationLog::TYPE_AGREEMENT, (int) $agreement->id,
                'agreement.expiry',
                'Agreement expiring', "Agreement #{$agreement->id} for {$customer}", $agreement->end_date);
        }
    }

    /**
     * Build and send one reminder, unless an identical reminder for this entity
     * already went out today (idempotency).
     */
    private function send(
        Tenant $tenant,
        TenantUser $admin,
        string $notifiableType,
        int $notifiableId,
        string $eventType,
        string $heading,
        string $label,
        mixed $expiryDate,
    ): void {
        $alreadySent = NotificationLog::query()
            ->forNotifiable($notifiableType, $notifiableId)
            ->where('event_type', $eventType)
            ->whereDate('created_at', Carbon::today())
            ->exists();

        if ($alreadySent) {
            return;
        }

        $content = (new ExpiryReminderTemplate())->build($heading, $label, $expiryDate);

        $this->notifications->sendEmail(
            $tenant,
            $admin->email,
            $content->subject,
            $content->emailBody,
            $eventType,
            $notifiableId,
            $notifiableType,
        );
    }

    private function matches(mixed $date, Carbon $target): bool
    {
        return $date !== null && Carbon::parse($date)->isSameDay($target);
    }
}
