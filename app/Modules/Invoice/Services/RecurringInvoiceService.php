<?php

namespace App\Modules\Invoice\Services;

use App\Jobs\GenerateInvoicePdfJob;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Invoice\DTOs\CreateInvoiceDTO;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates recurring invoices from agreements (the source of truth — CLAUDE.md).
 *
 * Two entry points:
 *   - generateForAgreement(): the FIRST invoice for an agreement, covering the
 *     period that starts at its start_date. Session 14 wires this to an
 *     AgreementSigned listener; for now it is a callable method.
 *   - generateDue(): the scheduled-command path. Across every tenant, it finds
 *     agreements whose next_billing_date has arrived, raises the next period's
 *     invoice, and advances next_billing_date. It NEVER throws and NEVER lets one
 *     failure abort the batch — each failure is logged and the loop continues.
 *
 * All amounts are integer cents. Invoice creation (header + items + ledger entry)
 * is delegated to CreateInvoiceService; PDF generation is always queued.
 */
class RecurringInvoiceService extends BaseService
{
    /** Agreement statuses that bill recurringly. There is no activation
     *  transition yet (later session), so a freshly-signed agreement counts. */
    private const BILLABLE_STATUSES = [
        Agreement::STATUS_SIGNED,
        Agreement::STATUS_ACTIVE,
    ];

    public function __construct(
        private readonly CreateInvoiceService $invoices,
        private readonly NextBillingDateService $nextBilling,
    ) {}

    /**
     * Create the first recurring invoice for an agreement — the period beginning
     * at its start_date. Does NOT touch next_billing_date: that is set by
     * AgreementService::sign() (the date the SECOND invoice falls due).
     */
    public function generateForAgreement(Agreement $agreement): Invoice
    {
        return $this->generateForPeriod($agreement, $agreement->start_date->copy());
    }

    /**
     * Scheduled path. For every tenant, raise invoices for agreements whose
     * next_billing_date is today or earlier, then roll the date forward.
     *
     * Runs with NO bound tenant (cron/queue context), so it binds each tenant in
     * turn before any tenant-scoped query — a bare Agreement::query() would throw
     * TenantNotResolvedException otherwise.
     */
    public function generateDue(): void
    {
        $today = Carbon::today();

        foreach (Tenant::all() as $tenant) {
            app()->instance('current_tenant', $tenant);

            try {
                $agreements = Agreement::query()
                    ->whereIn('status', self::BILLABLE_STATUSES)
                    ->whereNotNull('next_billing_date')
                    ->whereDate('next_billing_date', '<=', $today)
                    ->get();

                foreach ($agreements as $agreement) {
                    $this->generateNextFor($agreement);
                }
            } catch (Throwable $e) {
                // Tenant-level failure (e.g. the query itself) — log and move on
                // so one tenant never blocks the rest of the platform's billing.
                Log::error('RecurringInvoiceService: tenant batch failed', [
                    'tenant_id' => $tenant->id,
                    'message' => $e->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('current_tenant');
            }
        }
    }

    /**
     * Raise the due invoice for a single agreement and advance its
     * next_billing_date. Isolated try/catch: one bad agreement is logged and
     * skipped, never aborting the tenant's batch.
     */
    private function generateNextFor(Agreement $agreement): void
    {
        try {
            $periodStart = $agreement->next_billing_date->copy();

            $this->generateForPeriod($agreement, $periodStart);

            // Advance to the following period. next_billing_date is operational
            // metadata, not a term, so updating it does not breach immutability.
            $agreement->update([
                'next_billing_date' => $this->nextBilling
                    ->calculate($agreement, $periodStart)
                    ->toDateString(),
            ]);
        } catch (Throwable $e) {
            Log::error('RecurringInvoiceService: agreement billing failed', [
                'tenant_id' => $agreement->tenant_id,
                'agreement_id' => $agreement->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build and persist one recurring invoice covering [periodStart, periodEnd],
     * where periodEnd is the day before the next billing date. The single line
     * item charges the agreement's full cycle rate. Returns the invoice and
     * queues its PDF.
     */
    private function generateForPeriod(Agreement $agreement, Carbon $periodStart): Invoice
    {
        // The period runs up to the day before the next cycle begins.
        $periodEnd = $this->nextBilling->calculate($agreement, $periodStart)
            ->copy()
            ->subDay();

        $dto = new CreateInvoiceDTO(
            customer_id: (int) $agreement->customer_id,
            agreement_id: (int) $agreement->id,
            items: [[
                'description' => "Rental — {$agreement->billing_cycle} charge",
                'amount' => (int) $agreement->rate,
                'vehicle_id' => $agreement->vehicle_id,
                'period_start' => $periodStart->toDateString(),
                'period_end' => $periodEnd->toDateString(),
            ]],
            type: Invoice::TYPE_RECURRING,
            billing_period_start: $periodStart->toDateString(),
            billing_period_end: $periodEnd->toDateString(),
            due_date: $periodStart->toDateString(),
        );

        $invoice = $this->invoices->execute($dto);

        // PDF is ALWAYS queued, never synchronous (CLAUDE.md). Pass ids only —
        // the job re-resolves under the tenant scope it binds for itself.
        GenerateInvoicePdfJob::dispatch($invoice->id, $invoice->tenant_id);

        return $invoice;
    }
}
