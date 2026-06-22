<?php

namespace App\Modules\Invoice\Actions;

use App\Actions\BaseAction;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Invoice\Events\LateFeeApplied;
use App\Modules\Invoice\Models\Invoice;
use Illuminate\Support\Facades\DB;

/**
 * Applies a single late fee to an invoice — the ONE sanctioned path for doing so.
 *
 * The fee is read from the tenant's settings (per-tenant config, CLAUDE.md
 * "configurable grace period + amount/%"):
 *   settings.late_fee_type        'fixed' | 'percentage'   (default 'fixed')
 *   settings.late_fee_amount      integer cents            (fixed; default 5000 = $50)
 *   settings.late_fee_percentage  integer 0–100            (percentage of invoice total)
 *
 * Effects (atomic):
 *   - a new InvoiceItem line for the fee (description prefixed "Late fee" — the
 *     idempotency marker LateFeeService relies on to avoid stacking fees);
 *   - the invoice subtotal/total bumped by the fee;
 *   - a positive late_fee ledger entry (customer owes more);
 *   - status → overdue;
 *   - LateFeeApplied fired.
 *
 * All amounts are integer cents. Invoices are mutable financial documents (unlike
 * the append-only ledger), so adjusting subtotal/total/status here is correct.
 */
class ApplyLateFeeAction extends BaseAction
{
    /** Stable prefix used both to label the line and to detect an existing fee. */
    public const LATE_FEE_DESCRIPTION = 'Late fee';

    private const DEFAULT_FIXED_FEE = 5000; // $50 AUD in cents

    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    public function execute(Invoice $invoice): Invoice
    {
        $fee = $this->resolveFee($invoice);

        return DB::transaction(function () use ($invoice, $fee): Invoice {
            $invoice->items()->create([
                'description' => self::LATE_FEE_DESCRIPTION,
                'amount' => $fee,
                'vehicle_id' => null,
                'period_start' => null,
                'period_end' => null,
            ]);

            $invoice->update([
                'subtotal' => $invoice->subtotal + $fee,
                'total' => $invoice->total + $fee,
                'status' => Invoice::STATUS_OVERDUE,
            ]);

            $this->ledger->append(
                tenantId: $invoice->tenant_id,
                customerId: $invoice->customer_id,
                type: LedgerEntry::TYPE_LATE_FEE,
                amount: $fee,
                description: "Late fee on Invoice #{$invoice->id}",
                referenceType: 'invoice',
                referenceId: $invoice->id,
            );

            LateFeeApplied::dispatch($invoice, $fee);

            return $invoice;
        });
    }

    /**
     * Compute the fee in cents from the tenant's settings. Percentage fees are
     * taken on the invoice total; a fixed fee (or missing config) falls back to
     * the $50 default.
     */
    private function resolveFee(Invoice $invoice): int
    {
        // The tenant is bound by LateFeeService while iterating; fall back to the
        // invoice's own tenant relationship for robustness.
        $settings = (app()->bound('current_tenant')
            ? app('current_tenant')->settings
            : $invoice->tenant?->settings) ?? [];

        $type = $settings['late_fee_type'] ?? 'fixed';

        if ($type === 'percentage') {
            $pct = (int) ($settings['late_fee_percentage'] ?? 0);

            if ($pct > 0) {
                // Integer cents: percentage of the invoice total.
                return intdiv((int) $invoice->total * $pct, 100);
            }
        }

        return (int) ($settings['late_fee_amount'] ?? self::DEFAULT_FIXED_FEE);
    }
}
