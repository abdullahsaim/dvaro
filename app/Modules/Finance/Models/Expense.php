<?php

namespace App\Modules\Finance\Models;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\TenantUser;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company (tenant-level) expense — daily costs, government fees, utilities,
 * or any tenant category. Optionally linked to a vehicle (profit per vehicle).
 *
 * MONEY IN CENTS. amount_total is GST-INCLUSIVE; amount_ex_gst is derived
 * (total − GST). Written ONLY through Record/Update/VoidExpenseAction, which
 * keep the append-only ledger in step (TYPE_EXPENSE, no customer).
 *
 * Never deleted: voiding stamps voided_at/by/reason and appends a reversing
 * ledger entry. Voided expenses are excluded from every total (scopeActive).
 */
class Expense extends Model
{
    use HasTenant;

    public const METHOD_CASH = 'cash';
    public const METHOD_CARD = 'card';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_OTHER = 'other';

    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_OTHER,
    ];

    protected $fillable = [
        'tenant_id',
        'expense_category_id',
        'vehicle_id',
        'expense_date',
        'description',
        'supplier',
        'amount_total',
        'gst_amount',
        'amount_ex_gst',
        'includes_gst',
        'payment_method',
        'notes',
        'created_by',
        // receipt_path + void columns are NOT fillable — actions set them.
    ];

    /** Internal storage path never leaves the server; UI gets has_receipt. */
    protected $hidden = ['receipt_path'];

    protected $appends = ['has_receipt'];

    protected function casts(): array
    {
        return [
            'expense_date' => 'date',
            'amount_total' => 'integer',
            'gst_amount' => 'integer',
            'amount_ex_gst' => 'integer',
            'includes_gst' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('voided_at');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function getHasReceiptAttribute(): bool
    {
        return $this->receipt_path !== null;
    }
}
