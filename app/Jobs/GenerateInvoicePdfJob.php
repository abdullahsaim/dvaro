<?php

namespace App\Jobs;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Generates an invoice PDF and stores it on S3, then records the path on the
 * invoice. PDF generation is ALWAYS queued, never synchronous (CLAUDE.md).
 * Rendering is pure-PHP via dompdf (no Chromium dependency) — same approach as
 * GenerateAgreementPdfJob.
 *
 * Runs with NO bound tenant (queue worker context). It binds current_tenant for
 * the duration of the job — so Invoice/Customer/Payment global scopes resolve —
 * and forgets it afterwards so nothing leaks to the next job.
 *
 * FAILURE POLICY: on any error this logs and returns — it never throws. A failed
 * PDF must not break the billing flow; the invoice and its ledger entry are
 * already persisted. tries=1 so a swallowed failure does not retry-storm.
 */
class GenerateInvoicePdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $invoiceId,
        public readonly int $tenantId,
    ) {
        // PDF always queued on the dedicated 'pdf' queue (CLAUDE.md).
        $this->onQueue('pdf');
    }

    public function handle(): void
    {
        try {
            $tenant = Tenant::find($this->tenantId);

            if ($tenant === null) {
                Log::warning('GenerateInvoicePdfJob: tenant not found', [
                    'tenant_id' => $this->tenantId,
                    'invoice_id' => $this->invoiceId,
                ]);

                return;
            }

            app()->instance('current_tenant', $tenant);

            $invoice = Invoice::with(['customer', 'items.vehicle', 'payments'])
                ->find($this->invoiceId);

            if ($invoice === null) {
                Log::warning('GenerateInvoicePdfJob: invoice not found', [
                    'tenant_id' => $this->tenantId,
                    'invoice_id' => $this->invoiceId,
                ]);

                return;
            }

            $pdf = Pdf::loadView('pdf.invoice', [
                'invoice' => $invoice,
                'tenant' => $tenant,
            ]);

            // tenants/{tenant_id}/invoices/{invoice_id}/invoice.pdf
            $path = "tenants/{$this->tenantId}/invoices/{$invoice->id}/invoice.pdf";

            Storage::disk('s3')->put($path, $pdf->output());

            // pdf_path is an ordinary column — recording it is not a financial edit.
            $invoice->update(['pdf_path' => $path]);
        } catch (\Throwable $e) {
            Log::error('GenerateInvoicePdfJob failed', [
                'tenant_id' => $this->tenantId,
                'invoice_id' => $this->invoiceId,
                'message' => $e->getMessage(),
            ]);
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }
}
