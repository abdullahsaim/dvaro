<?php

namespace App\Modules\Fleet\Models;

use App\Modules\Agreement\Models\Agreement;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        ];
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
