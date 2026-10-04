<?php

namespace App\Modules\Rental\Models;

use App\Exceptions\AppendOnlyException;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\TenantUser;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The permanent record of a vehicle's return at the end of a rental:
 * odometer, fuel, condition, any damage, and the bond deduction/refund it
 * resulted in.
 *
 * APPEND-ONLY, like the ledger and the audit log (CLAUDE.md) — once filed,
 * this IS what happened at return. A correction is a new business decision
 * (e.g. a manual ledger adjustment with its own record), never a rewrite of
 * this row. One per agreement (DB-unique on agreement_id) — a rental is
 * returned at most once.
 */
class ReturnInspection extends Model
{
    use HasTenant;

    public const UPDATED_AT = null;

    public const FUEL_EMPTY = 'empty';

    public const FUEL_QUARTER = 'quarter';

    public const FUEL_HALF = 'half';

    public const FUEL_THREE_QUARTER = 'three_quarter';

    public const FUEL_FULL = 'full';

    public const FUEL_LEVELS = [
        self::FUEL_EMPTY,
        self::FUEL_QUARTER,
        self::FUEL_HALF,
        self::FUEL_THREE_QUARTER,
        self::FUEL_FULL,
    ];

    protected $fillable = [
        'tenant_id',
        'agreement_id',
        'vehicle_id',
        'customer_id',
        'odometer_reading',
        'fuel_level',
        'condition_notes',
        'damage_found',
        'damage_description',
        'needs_workshop',
        'deduction_amount',
        'deduction_reason',
        'bond_amount',
        'refund_amount',
        'inspected_by',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'odometer_reading' => 'integer',
            'damage_found' => 'boolean',
            'needs_workshop' => 'boolean',
            'deduction_amount' => 'integer',
            'bond_amount' => 'integer',
            'refund_amount' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw AppendOnlyException::modify('Return inspections'));
        static::deleting(fn () => throw AppendOnlyException::remove('Return inspections'));
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw AppendOnlyException::modify('Return inspections');
    }

    public function delete(): ?bool
    {
        throw AppendOnlyException::remove('Return inspections');
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'inspected_by');
    }
}
