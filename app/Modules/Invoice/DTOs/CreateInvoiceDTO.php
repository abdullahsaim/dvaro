<?php

namespace App\Modules\Invoice\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated invoice-creation data into CreateInvoiceService.
 *
 * Foundation only: no prorated/late-fee fields. The invoice total is derived
 * from the sum of $items amounts by the service — callers do not pass a total.
 *
 * Each item is an associative array:
 *   [
 *     'description'  => string,
 *     'amount'       => int,        // cents
 *     'vehicle_id'   => int|null,
 *     'period_start' => string|null, // date
 *     'period_end'   => string|null, // date
 *   ]
 */
class CreateInvoiceDTO extends BaseDTO
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly int $customer_id,
        public readonly ?int $agreement_id,
        public readonly array $items,
        public readonly string $type = 'manual',
        public readonly ?string $billing_period_start = null,
        public readonly ?string $billing_period_end = null,
        public readonly ?string $due_date = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            customer_id: (int) $request->input('customer_id'),
            agreement_id: $request->filled('agreement_id') ? (int) $request->input('agreement_id') : null,
            items: $request->input('items', []),
            type: $request->string('type', 'manual')->toString(),
            billing_period_start: $request->input('billing_period_start'),
            billing_period_end: $request->input('billing_period_end'),
            due_date: $request->input('due_date'),
        );
    }
}
