<?php

namespace App\Modules\Invoice\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Models\TenantUser;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Payment — a single payment recorded against an invoice.
 *
 * Tenant-owned: uses HasTenant so every query is scoped to the bound tenant and
 * tenant_id is auto-populated on create. amount is in CENTS (integer).
 *
 * This session records MANUAL payments only (cash / bank transfer); the gateway
 * methods + gateway_payment_id are present for the future Stripe/PayPal session.
 * Recording a payment is done exclusively through RecordPaymentAction, which also
 * posts the ledger entry and updates the invoice — never create a Payment row
 * directly elsewhere.
 */
class Payment extends Model
{
    use HasTenant;

    public const METHOD_CASH = 'cash';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_STRIPE = 'stripe';
    public const METHOD_PAYPAL = 'paypal';

    /** Every valid payment method. */
    public const METHODS = [
        self::METHOD_CASH,
        self::METHOD_BANK_TRANSFER,
        self::METHOD_STRIPE,
        self::METHOD_PAYPAL,
    ];

    protected $fillable = [
        'tenant_id',
        'invoice_id',
        'customer_id',
        'amount',
        'method',
        'gateway_payment_id',
        'notes',
        'recorded_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer', // cents
            'paid_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** The tenant user who recorded the payment, or null when system/removed. */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'recorded_by');
    }
}
