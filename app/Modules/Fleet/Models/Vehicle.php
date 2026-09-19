<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Workshop\Models\ServiceLog;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Vehicle — a tenant's fleet vehicle.
 *
 * Tenant-owned: uses HasTenant so every query is constrained to the bound
 * tenant and tenant_id is auto-populated on create.
 *
 * Status lifecycle: the `status` column must ONLY ever be changed through
 * App\Modules\Fleet\Actions\ChangeVehicleStatusAction, which validates the
 * target status and fires VehicleStatusChanged. Never call
 * $vehicle->update(['status' => ...]) or set $vehicle->status directly
 * anywhere else in the codebase.
 */
class Vehicle extends Model
{
    use HasTenant;
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';
    public const STATUS_RENTED = 'rented';
    public const STATUS_MAINTENANCE = 'maintenance';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ACCIDENT = 'accident';
    public const STATUS_RESERVED = 'reserved';

    /** The complete set of valid vehicle statuses (CLAUDE.md — 6 statuses). */
    public const STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_RENTED,
        self::STATUS_MAINTENANCE,
        self::STATUS_SUSPENDED,
        self::STATUS_ACCIDENT,
        self::STATUS_RESERVED,
    ];

    /**
     * Default reminder lead times — how far ahead a date / service km counts as
     * "due soon". Tenants override via settings.fleet_reminder_days / _km; the
     * fleet list badges, "Expiring soon" filter and FleetReminderService all
     * read the SAME window (reminderLeadDays() / reminderLeadKm()).
     */
    public const DEFAULT_REMINDER_DAYS = 30;
    public const DEFAULT_REMINDER_KM = 1000;

    /** Date columns the fleet list may sort by (whitelist — never raw input). */
    public const SORTABLE_DATES = ['registration_expiry', 'next_service_due'];

    public const EXPIRY_OVERDUE = 'overdue';
    public const EXPIRY_DUE_SOON = 'due_soon';
    public const EXPIRY_OK = 'ok';

    protected $fillable = [
        'tenant_id',
        'registration_number',
        'make',
        'model',
        'year',
        'status',
        'daily_rate',
        'insurance_company',
        'insurance_expiry',
        'registration_expiry',
        'last_service_date',
        'next_service_due',
        'qr_code_token',
        'notes',
        'service_interval_months',
        'service_interval_km',
        'last_service_odometer',
        // current_odometer / odometer_updated_at / next_service_km are
        // deliberately NOT fillable: odometer changes only via
        // RecordOdometerReadingAction; next_service_km is derived
        // (applyServiceSchedule).
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'daily_rate' => 'integer', // cents
            'insurance_expiry' => 'date',
            'registration_expiry' => 'date',
            'last_service_date' => 'date',
            'next_service_due' => 'date',
            'current_odometer' => 'integer',
            'odometer_updated_at' => 'datetime',
            'service_interval_months' => 'integer',
            'service_interval_km' => 'integer',
            'last_service_odometer' => 'integer',
            'next_service_km' => 'integer',
        ];
    }

    /**
     * Lead time (days) for "due soon" — the bound tenant's setting, else default.
     */
    public static function reminderLeadDays(): int
    {
        $days = app()->bound('current_tenant')
            ? (app('current_tenant')->settings['fleet_reminder_days'] ?? null)
            : null;

        return is_numeric($days) ? max(1, (int) $days) : self::DEFAULT_REMINDER_DAYS;
    }

    /**
     * Lead distance (km) for "due soon" — the bound tenant's setting, else default.
     */
    public static function reminderLeadKm(): int
    {
        $km = app()->bound('current_tenant')
            ? (app('current_tenant')->settings['fleet_reminder_km'] ?? null)
            : null;

        return is_numeric($km) ? max(1, (int) $km) : self::DEFAULT_REMINDER_KM;
    }

    /**
     * Derive the next service from the intervals (whichever comes first):
     *   next_service_due = last_service_date + service_interval_months
     *   next_service_km  = last_service_odometer + service_interval_km
     * A missing interval leaves that side as-is (a manually entered
     * next_service_due is kept when no months interval is configured);
     * next_service_km is cleared when it can't be derived. Does NOT save.
     */
    public function applyServiceSchedule(): static
    {
        if ($this->service_interval_months && $this->last_service_date) {
            $this->next_service_due = $this->last_service_date->copy()
                ->addMonthsNoOverflow($this->service_interval_months);
        }

        $this->next_service_km = ($this->service_interval_km && $this->last_service_odometer !== null)
            ? $this->last_service_odometer + $this->service_interval_km
            : null;

        return $this;
    }

    public function odometerReadings(): HasMany
    {
        return $this->hasMany(OdometerReading::class);
    }

    // The tenant() relationship is provided by the HasTenant trait.

    /**
     * Agreements that reference this vehicle. Read-only convenience: agreements
     * are the source of truth and are never created from here.
     */
    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }

    /**
     * Workshop service logs for this vehicle (newest first when iterated via the
     * lastServiceLog convenience below).
     */
    public function serviceLogs(): HasMany
    {
        return $this->hasMany(ServiceLog::class);
    }

    /**
     * The most recent service log, if any.
     */
    public function lastServiceLog(): HasOne
    {
        return $this->hasOne(ServiceLog::class)->latestOfMany();
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AVAILABLE);
    }

    public function scopeRented(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_RENTED);
    }

    /**
     * Vehicles whose next service is due on or before today.
     */
    public function scopeNeedsService(Builder $query): Builder
    {
        return $query->whereNotNull('next_service_due')
            ->whereDate('next_service_due', '<=', now());
    }

    /**
     * Registration or next service (by date OR by km) is within the tenant's
     * reminder lead time, or already past. Vehicles with nothing recorded are
     * excluded. Pass $dates to restrict which date columns count (the reminder
     * sweep also includes insurance_expiry).
     *
     * @param  list<string>  $dates
     */
    public function scopeExpiringSoon(
        Builder $query,
        array $dates = ['registration_expiry', 'next_service_due'],
    ): Builder {
        $cutoff = today()->addDays(self::reminderLeadDays());
        $leadKm = self::reminderLeadKm();

        return $query->where(function (Builder $q) use ($dates, $cutoff, $leadKm) {
            foreach ($dates as $column) {
                $q->orWhereDate($column, '<=', $cutoff);
            }

            $q->orWhere(fn (Builder $km) => $km
                ->whereNotNull('next_service_km')
                ->whereNotNull('current_odometer')
                ->whereRaw('current_odometer + ? >= next_service_km', [$leadKm]));
        });
    }

    /**
     * overdue | due_soon | ok for a date column, or null when no date is set.
     * Drives the fleet list badges + reminders (server-side, app timezone).
     * Overdue = strictly past (due today is still "due soon").
     */
    public function expiryState(string $column): ?string
    {
        $date = $this->{$column};

        if ($date === null) {
            return null;
        }

        if ($date->lt(today())) {
            return self::EXPIRY_OVERDUE;
        }

        return $date->lte(today()->addDays(self::reminderLeadDays()))
            ? self::EXPIRY_DUE_SOON
            : self::EXPIRY_OK;
    }

    /**
     * overdue | due_soon | ok for the km side of the service schedule, or null
     * when either the target km or the current odometer is unknown.
     * Overdue = odometer has reached/passed next_service_km.
     */
    public function serviceKmState(): ?string
    {
        if ($this->next_service_km === null || $this->current_odometer === null) {
            return null;
        }

        if ($this->current_odometer >= $this->next_service_km) {
            return self::EXPIRY_OVERDUE;
        }

        return $this->current_odometer + self::reminderLeadKm() >= $this->next_service_km
            ? self::EXPIRY_DUE_SOON
            : self::EXPIRY_OK;
    }

    /**
     * The more urgent of the date and km service states ("whichever first").
     */
    public function serviceState(): ?string
    {
        $rank = [self::EXPIRY_OVERDUE => 3, self::EXPIRY_DUE_SOON => 2, self::EXPIRY_OK => 1];

        return collect([$this->expiryState('next_service_due'), $this->serviceKmState()])
            ->filter()
            ->sortByDesc(fn (string $state) => $rank[$state])
            ->first();
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_AVAILABLE;
    }

    /**
     * True when insurance expires within the given window (default 30 days).
     * False when no expiry date is recorded.
     */
    public function insuranceExpiringSoon(int $days = 30): bool
    {
        return $this->insurance_expiry !== null
            && $this->insurance_expiry->lte(now()->addDays($days));
    }

    /**
     * True when registration expires within the given window (default 30 days).
     * False when no expiry date is recorded.
     */
    public function registrationExpiringSoon(int $days = 30): bool
    {
        return $this->registration_expiry !== null
            && $this->registration_expiry->lte(now()->addDays($days));
    }
}
