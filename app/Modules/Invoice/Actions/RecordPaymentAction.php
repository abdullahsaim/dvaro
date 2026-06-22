<?php

namespace App\Modules\Invoice\Actions;

use App\Actions\BaseAction;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Invoice\DTOs\RecordPaymentDTO;
use App\Modules\Invoice\Events\PaymentReceived;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Records a manual payment (cash / bank transfer) against an invoice — the ONE
 * sanctioned path. Online gateway payments (Stripe/PayPal) come later and will
 * reuse this same action.
 *
 * Effects (atomic):
 *   - a Payment row;
 *   - a NEGATIVE payment ledger entry (a payment REDUCES the balance — CLAUDE.md
 *     sign convention: negative = credit/payment);
 *   - the invoice paid_amount increased; status → paid once fully covered;
 *   - PaymentReceived fired.
 *
 * All amounts are integer cents. Invoices are mutable (unlike the append-only
 * ledger), so updating paid_amount/status here is correct.
 */
class RecordPaymentAction extends BaseAction
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(RecordPaymentDTO $dto): Invoice
    {
        // Tenant-scoped lookup (HasTenant) — a cross-tenant id resolves to null.
        $invoice = Invoice::findOrFail($dto->invoice_id);

        // A cancelled invoice cannot take a payment. The request validates this
        // for a friendly error; this is the authoritative backstop.
        if ($invoice->status === Invoice::STATUS_CANCELLED) {
            throw new RuntimeException('A cancelled invoice cannot be paid.');
        }

        return DB::transaction(function () use ($dto, $invoice): Invoice {
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $dto->customer_id,
                'amount' => $dto->amount,
                'method' => $dto->method,
                'gateway_payment_id' => null, // manual payment
                'notes' => $dto->notes,
                'recorded_by' => auth('tenant')->id(),
                'paid_at' => $dto->paid_at ?? now(),
            ]);

            // Payment REDUCES the balance → negative ledger amount.
            $this->ledger->append(
                tenantId: $invoice->tenant_id,
                customerId: $invoice->customer_id,
                type: LedgerEntry::TYPE_PAYMENT,
                amount: -$dto->amount,
                description: "Payment for Invoice #{$invoice->id} ({$dto->method})",
                referenceType: 'payment',
                referenceId: $payment->id,
            );

            $paidAmount = $invoice->paid_amount + $dto->amount;

            $invoice->update([
                'paid_amount' => $paidAmount,
                'status' => $paidAmount >= $invoice->total
                    ? Invoice::STATUS_PAID
                    : $invoice->status,
            ]);

            PaymentReceived::dispatch($invoice, $payment);

            return $invoice;
        });
    }
}
