<?php

namespace App\Modules\SaasCore\Models;

use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SubscriptionPayment — an OFFLINE payment recorded by a super admin against a
 * tenant's subscription (bank transfer, cash, invoiced payment, etc.).
 *
 * Tenant-owned (HasTenant): tenant_id is auto-populated when a tenant is bound,
 * but super admins record these with NO bound tenant, so tenant_id is passed
 * explicitly on create (the INSERT skips TenantScope). amount is in CENTS.
 *
 * The gateway methods (stripe/paypal) exist for the future Session C; this
 * session records offline methods only.
 */
class SubscriptionPayment extends Model
{
    use HasTenant;

    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CASH = 'cash';
    public const METHOD_STRIPE = 'stripe';
    public const METHOD_PAYPAL = 'paypal';
    public const METHOD_OTHER = 'other';

    /** Every valid payment method. */
    public const METHODS = [
        self::METHOD_BANK_TRANSFER,
        self::METHOD_CASH,
        self::METHOD_STRIPE,
        self::METHOD_PAYPAL,
        self::METHOD_OTHER,
    ];

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'amount',
        'currency',
        'method',
        'reference',
        'notes',
        'paid_at',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', // cents
            'paid_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /** The super admin who recorded the payment, or null when removed. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'recorded_by');
    }
}
