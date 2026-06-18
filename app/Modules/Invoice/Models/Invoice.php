<?php

namespace App\Modules\Invoice\Models;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Invoice — a billable document generated from an agreement's billing settings
 * (CLAUDE.md: invoices are generated FROM the agreement, the source of truth).
 *
 * Tenant-owned: uses HasTenant so every query is scoped to the bound tenant and
 * tenant_id is auto-populated on create.
 *
 * Foundation only: this session creates the structure plus straightforward
 * invoice creation. Prorated splitting, late fees, and payment-gateway flows
 * live in later sessions. All amounts are in CENTS (integer).
 */
class Invoice extends Model
{
    use HasTenant;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_SENT,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELLED,
    ];

    public const TYPE_RECURRING = 'recurring';
    public const TYPE_MANUAL = 'manual';
    public const TYPE_PRORATED = 'prorated';

    public const TYPES = [
        self::TYPE_RECURRING,
        self::TYPE_MANUAL,
        self::TYPE_PRORATED,
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'agreement_id',
        'type',
        'status',
        'billing_period_start',
        'billing_period_end',
        'due_date',
        'subtotal',
        'total',
        'paid_amount',
        'pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'billing_period_start' => 'date',
            'billing_period_end' => 'date',
            'due_date' => 'date',
            'subtotal' => 'integer', // cents
            'total' => 'integer',    // cents
            'paid_amount' => 'integer', // cents
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Overdue when explicitly marked so, or still owing money past the due date.
     */
    public function isOverdue(): bool
    {
        if ($this->status === self::STATUS_OVERDUE) {
            return true;
        }

        return $this->outstandingAmount() > 0
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    /** Remaining amount owed on this invoice, in cents. */
    public function outstandingAmount(): int
    {
        return $this->total - $this->paid_amount;
    }
}
