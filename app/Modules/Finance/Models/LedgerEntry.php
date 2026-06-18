<?php

namespace App\Modules\Finance\Models;

use App\Exceptions\LedgerImmutableException;
use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LedgerEntry — the immutable, append-only system of record for every financial
 * event in a tenant (CLAUDE.md: "LEDGER = SYSTEM OF RECORD").
 *
 * Tenant-owned: uses HasTenant so every query is scoped to the bound tenant and
 * tenant_id is auto-populated on create.
 *
 * AMOUNTS ARE IN CENTS (integer). SIGN CONVENTION:
 *   POSITIVE = debit/charge  → customer owes MORE
 *   NEGATIVE = credit/payment → customer owes LESS
 * A customer balance is SUM(amount) over their entries (see LedgerService).
 *
 * APPEND-ONLY — enforced in code, not by convention. Entries may only be
 * created. update() and delete() throw LedgerImmutableException at every layer:
 *   1. the instance methods overridden below,
 *   2. the `updating`/`deleting` model events (covers $entry->save() on an
 *      already-persisted row),
 *   3. the custom LedgerEntryBuilder (covers query-builder mass update/delete).
 *
 * The ONLY sanctioned way to create an entry is LedgerService::append().
 */
class LedgerEntry extends Model
{
    use HasTenant;

    /** Append-only: created_at is meaningful, updated_at is never written. */
    public const UPDATED_AT = null;

    public const TYPE_RENTAL_CHARGE = 'rental_charge';
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_LATE_FEE = 'late_fee';
    public const TYPE_DISCOUNT = 'discount';
    public const TYPE_BOND_COLLECTION = 'bond_collection';
    public const TYPE_BOND_REFUND = 'bond_refund';
    public const TYPE_BOND_DEDUCTION = 'bond_deduction';
    public const TYPE_EXPENSE = 'expense';
    public const TYPE_REFUND = 'refund';

    /** Every valid ledger entry type. */
    public const TYPES = [
        self::TYPE_RENTAL_CHARGE,
        self::TYPE_PAYMENT,
        self::TYPE_LATE_FEE,
        self::TYPE_DISCOUNT,
        self::TYPE_BOND_COLLECTION,
        self::TYPE_BOND_REFUND,
        self::TYPE_BOND_DEDUCTION,
        self::TYPE_EXPENSE,
        self::TYPE_REFUND,
    ];

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'description',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', // cents
        ];
    }

    protected static function booted(): void
    {
        // Defence-in-depth: block the model-event update/delete paths (e.g. a
        // $entry->save() on an existing row). Creation fires `creating`/`created`,
        // which are intentionally left untouched so append() works.
        static::updating(function (): void {
            throw LedgerImmutableException::modify();
        });

        static::deleting(function (): void {
            throw LedgerImmutableException::remove();
        });
    }

    /** Route all queries through the immutability-enforcing builder. */
    public function newEloquentBuilder($query): LedgerEntryBuilder
    {
        return new LedgerEntryBuilder($query);
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The user who created the entry, or null when system-generated. */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForCustomer(Builder $query, int $customerId): Builder
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Append-only guard — the instance update() path always throws.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw LedgerImmutableException::modify();
    }

    /** Append-only guard — the instance delete() path always throws. */
    public function delete(): ?bool
    {
        throw LedgerImmutableException::remove();
    }
}
