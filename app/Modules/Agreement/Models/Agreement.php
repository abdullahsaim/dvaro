<?php

namespace App\Modules\Agreement\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Agreement — the source of truth for a rental (CLAUDE.md).
 *
 * Tenant-owned: uses HasTenant so every query is constrained to the bound
 * tenant and tenant_id is auto-populated on create.
 *
 * Agreements are immutable — a change produces a NEW version row that points
 * back at its predecessor via parent_agreement_id. This model is the data
 * record only; versioning/signature/PDF workflows live in later sessions.
 */
class Agreement extends Model
{
    use HasTenant;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_PRIVATE = 'private';
    public const TYPE_DELIVERY = 'delivery';
    public const TYPE_RIDESHARE = 'rideshare';

    public const BILLING_DAILY = 'daily';
    public const BILLING_WEEKLY = 'weekly';
    public const BILLING_MONTHLY = 'monthly';

    /**
     * Allowed agreement types — used by validation (StoreAgreementRequest) and
     * the create form. Mirrors the TYPE_* constants above.
     */
    public const TYPES = [
        self::TYPE_PRIVATE,
        self::TYPE_DELIVERY,
        self::TYPE_RIDESHARE,
    ];

    /**
     * Allowed billing cycles — used by validation and the create form. Mirrors
     * the BILLING_* constants above.
     */
    public const BILLING_CYCLES = [
        self::BILLING_DAILY,
        self::BILLING_WEEKLY,
        self::BILLING_MONTHLY,
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'vehicle_id',
        'type',
        'status',
        'version',
        'parent_agreement_id',
        'billing_cycle',
        'billing_cycle_day',
        'rate',
        'bond_amount',
        'start_date',
        'end_date',
        'next_billing_date',
        'notes',
        'signed_at',
        'signature_data',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'rate' => 'integer',
            'bond_amount' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_billing_date' => 'date',
            'signed_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * The vehicle this agreement covers. The FK (restrictOnDelete) was added
     * once the Fleet module's vehicles table existed.
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * The previous version this agreement was derived from (null on the first).
     */
    public function parentAgreement(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_agreement_id');
    }

    /**
     * Newer versions derived directly from this agreement.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_agreement_id');
    }

    public function isSigned(): bool
    {
        return $this->status === self::STATUS_SIGNED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * True when no newer version references this agreement as its parent.
     */
    public function latestVersion(): bool
    {
        return ! $this->versions()->exists();
    }
}
