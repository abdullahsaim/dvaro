<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Invoice\DTOs\CreateInvoiceDTO;
use App\Modules\Invoice\Events\InvoiceGenerated;
use App\Modules\Invoice\Models\Invoice;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

/**
 * Creates an invoice (header + line items) and records the charge on the ledger,
 * atomically.
 *
 * Foundation only — straightforward creation: the invoice total is the sum of
 * its item amounts. No prorated splitting, no late fees, no payment processing.
 * No notifications are sent here; that is a listener on InvoiceGenerated in the
 * later Notification session.
 */
class CreateInvoiceService extends BaseService
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(CreateInvoiceDTO $dto): Invoice
    {
        return DB::transaction(function () use ($dto): Invoice {
            $total = array_sum(array_map(
                static fn (array $item): int => (int) $item['amount'],
                $dto->items,
            ));

            // HasTenant fills tenant_id from the bound tenant on create.
            $invoice = Invoice::create([
                'customer_id' => $dto->customer_id,
                'agreement_id' => $dto->agreement_id,
                'type' => $dto->type,
                'status' => Invoice::STATUS_DRAFT,
                'billing_period_start' => $dto->billing_period_start,
                'billing_period_end' => $dto->billing_period_end,
                'due_date' => $dto->due_date,
                'subtotal' => $total,
                'total' => $total,
                'paid_amount' => 0,
            ]);

            foreach ($dto->items as $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'amount' => (int) $item['amount'],
                    'vehicle_id' => $item['vehicle_id'] ?? null,
                    'period_start' => $item['period_start'] ?? null,
                    'period_end' => $item['period_end'] ?? null,
                ]);
            }

            // Record the charge on the ledger: positive amount = customer owes more.
            $this->ledger->append(
                tenantId: $invoice->tenant_id,
                customerId: $invoice->customer_id,
                type: LedgerEntry::TYPE_RENTAL_CHARGE,
                amount: $total,
                description: "Invoice #{$invoice->id}",
                referenceType: 'invoice',
                referenceId: $invoice->id,
            );

            InvoiceGenerated::dispatch($invoice);

            return $invoice;
        });
    }
}
