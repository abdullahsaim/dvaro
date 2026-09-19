<?php

namespace App\Modules\Notification\Services;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\FleetReminder;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Templates\FleetReminderDigestTemplate;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Daily fleet reminders — registration, insurance and service (by DATE or by
 * KM, whichever first) — sent to ALL of a tenant's staff as ONE digest email
 * per person per day (client decision: all staff, email only).
 *
 * Two stages per due value:
 *   due_soon — within the tenant's lead time (settings.fleet_reminder_days /
 *              fleet_reminder_km; defaults 30 days / 1,000 km)
 *   overdue  — date strictly past, or odometer reached/passed next_service_km
 * Each (vehicle, kind, stage, due value) is sent AT MOST ONCE, tracked in
 * fleet_reminders. A renewed rego date or a reset service schedule produces a
 * new due value, so reminders re-arm automatically.
 *
 * Runs from cron with NO bound tenant: binds each tenant before any scoped
 * query. NEVER throws — per-tenant try/catch so one tenant can't block the
 * platform. A tenant's items are only marked sent when at least one email was
 * delivered (a total provider failure retries next run).
 */
class FleetReminderService extends BaseService
{
    public const EVENT_TYPE = 'fleet.reminder_digest';

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function sweep(): void
    {
        foreach (Tenant::all() as $tenant) {
            app()->instance('current_tenant', $tenant);

            try {
                $this->sweepTenant($tenant);
            } catch (Throwable $e) {
                Log::error('FleetReminderService: tenant sweep failed', [
                    'tenant_id' => $tenant->id,
                    'message' => $e->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('current_tenant');
            }
        }
    }

    /**
     * Sweep ONE tenant (must already be bound). Returns the number of reminder
     * items sent (0 when disabled / nothing due / no recipients).
     */
    public function sweepTenant(Tenant $tenant): int
    {
        $settings = $tenant->settings ?? [];

        if (! (bool) ($settings['notify_email_enabled'] ?? true)
            || ! (bool) ($settings['fleet_reminders_enabled'] ?? true)) {
            return 0;
        }

        $recipients = TenantUser::query()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->get(['id', 'email']);

        if ($recipients->isEmpty()) {
            return 0;
        }

        $items = $this->pendingItems($tenant);

        if ($items === []) {
            return 0;
        }

        $content = (new FleetReminderDigestTemplate())->build(
            $tenant->name,
            array_map(fn (array $i) => $i['display'], $items),
        );

        $delivered = 0;
        foreach ($recipients as $user) {
            $ok = $this->notifications->sendEmail(
                $tenant,
                $user->email,
                $content->subject,
                $content->emailBody,
                self::EVENT_TYPE,
                (int) $user->id,
                NotificationLog::TYPE_TENANT_USER,
            );
            $delivered += $ok ? 1 : 0;
        }

        if ($delivered === 0) {
            return 0; // retry next run
        }

        foreach ($items as $item) {
            FleetReminder::create([
                ...$item['key'],
                'recipient_count' => $delivered,
                'sent_at' => now(),
            ]);
        }

        return count($items);
    }

    /**
     * Every due-soon / overdue item not yet reminded for its current due value.
     *
     * @return list<array{key: array<string, mixed>, display: array<string, mixed>}>
     */
    private function pendingItems(Tenant $tenant): array
    {
        $vehicles = Vehicle::query()
            ->expiringSoon(['registration_expiry', 'insurance_expiry', 'next_service_due'])
            ->orderBy('registration_number')
            ->get();

        if ($vehicles->isEmpty()) {
            return [];
        }

        $alreadySent = FleetReminder::query()
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->get(['vehicle_id', 'kind', 'stage', 'due_key'])
            ->map(fn (FleetReminder $r) => $this->signature($r->vehicle_id, $r->kind, $r->stage, $r->due_key))
            ->flip();

        $items = [];

        foreach ($vehicles as $vehicle) {
            foreach ($this->candidates($vehicle) as [$kind, $stage, $dueKey, $what, $due]) {
                if ($alreadySent->has($this->signature($vehicle->id, $kind, $stage, $dueKey))) {
                    continue;
                }

                $items[] = [
                    'key' => [
                        'vehicle_id' => $vehicle->id,
                        'kind' => $kind,
                        'stage' => $stage,
                        'due_key' => $dueKey,
                    ],
                    'display' => [
                        'rego' => $vehicle->registration_number,
                        'vehicle' => trim("{$vehicle->make} {$vehicle->model}"),
                        'what' => $what,
                        'due' => $due,
                        'overdue' => $stage === FleetReminder::STAGE_OVERDUE,
                        'url' => route('tenant.fleet.show', [
                            'tenant_slug' => $tenant->slug,
                            'vehicle' => $vehicle->id,
                        ]),
                    ],
                ];
            }
        }

        return $items;
    }

    /**
     * [kind, stage, due_key, what, due text] for each due-soon/overdue item.
     *
     * @return list<array{0:string,1:string,2:string,3:string,4:string}>
     */
    private function candidates(Vehicle $vehicle): array
    {
        $out = [];

        $dates = [
            FleetReminder::KIND_REGISTRATION => ['registration_expiry', 'Registration'],
            FleetReminder::KIND_INSURANCE => ['insurance_expiry', 'Insurance'],
            FleetReminder::KIND_SERVICE_DATE => ['next_service_due', 'Service (date)'],
        ];

        foreach ($dates as $kind => [$column, $label]) {
            $state = $vehicle->expiryState($column);

            if (! $this->isActionable($state)) {
                continue;
            }

            $date = Carbon::parse($vehicle->{$column});
            $out[] = [$kind, $state, $date->toDateString(), $label, $date->format('d/m/Y')];
        }

        $kmState = $vehicle->serviceKmState();

        if ($this->isActionable($kmState)) {
            $out[] = [
                FleetReminder::KIND_SERVICE_KM,
                $kmState,
                (string) $vehicle->next_service_km,
                'Service (km)',
                number_format($vehicle->next_service_km).' km (now '.number_format($vehicle->current_odometer).' km)',
            ];
        }

        return $out;
    }

    private function isActionable(?string $state): bool
    {
        return in_array($state, [Vehicle::EXPIRY_DUE_SOON, Vehicle::EXPIRY_OVERDUE], true);
    }

    private function signature(int $vehicleId, string $kind, string $stage, string $dueKey): string
    {
        return "{$vehicleId}|{$kind}|{$stage}|{$dueKey}";
    }
}
