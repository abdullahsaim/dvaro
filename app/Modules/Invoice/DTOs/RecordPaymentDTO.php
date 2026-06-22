<?php

namespace App\Modules\Invoice\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated manual-payment data into RecordPaymentAction.
 *
 * amount is integer CENTS. method is one of Payment::METHODS. paid_at defaults to
 * now when the form omits it. No tenant_id (HasTenant auto-fills on create).
 */
class RecordPaymentDTO extends BaseDTO
{
    public function __construct(
        public readonly int $invoice_id,
        public readonly int $customer_id,
        public readonly int $amount,
        public readonly string $method,
        public readonly ?string $notes = null,
        public readonly ?string $paid_at = null,
    ) {}

    public static function fromRequest(Request $request, int $invoiceId, int $customerId): self
    {
        return new self(
            invoice_id: $invoiceId,
            customer_id: $customerId,
            amount: (int) $request->integer('amount'),
            method: $request->string('method')->toString(),
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
            paid_at: $request->filled('paid_at') ? $request->string('paid_at')->toString() : null,
        );
    }
}
