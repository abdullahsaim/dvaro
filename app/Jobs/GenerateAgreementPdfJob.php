<?php

namespace App\Jobs;

use App\Modules\Agreement\Models\Agreement;
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
 * Generates the signed agreement PDF and stores it on S3, then records the path
 * on the agreement. PDF generation is ALWAYS queued, never synchronous
 * (CLAUDE.md). Rendering is pure-PHP via dompdf (no Chromium dependency).
 *
 * Runs with NO bound tenant (queue worker context). It binds current_tenant for
 * the duration of the job — so Agreement/Customer/Vehicle global scopes resolve
 * naturally — and forgets it afterwards so nothing leaks to the next job.
 *
 * FAILURE POLICY: on any error this logs and returns — it never throws. PDF
 * generation must not break or roll back the agreement signing flow; the
 * signature is already persisted by the time this runs. tries=1 so a swallowed
 * failure does not silently retry-storm.
 */
class GenerateAgreementPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $agreementId,
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
                Log::warning('GenerateAgreementPdfJob: tenant not found', [
                    'tenant_id' => $this->tenantId,
                    'agreement_id' => $this->agreementId,
                ]);

                return;
            }

            // Bind the tenant so TenantScope (on Agreement/Customer/Vehicle)
            // resolves; forget it in finally so the worker stays clean.
            app()->instance('current_tenant', $tenant);

            $agreement = Agreement::with(['customer', 'vehicle'])->find($this->agreementId);

            if ($agreement === null) {
                Log::warning('GenerateAgreementPdfJob: agreement not found', [
                    'tenant_id' => $this->tenantId,
                    'agreement_id' => $this->agreementId,
                ]);

                return;
            }

            $pdf = Pdf::loadView('pdf.agreement', ['agreement' => $agreement]);

            // tenants/{tenant_id}/agreements/{agreement_id}/agreement-v{version}.pdf
            $path = "tenants/{$this->tenantId}/agreements/{$agreement->id}"
                ."/agreement-v{$agreement->version}.pdf";

            // Default disk: 'local' (private) by default, 's3' once configured.
            // Sensitive — stored under the private root, never web-accessible.
            Storage::disk(config('filesystems.default'))->put($path, $pdf->output());

            // pdf_path is an ordinary column — recording it is not an "edit" of
            // the agreement's terms, just attaching the rendered artifact.
            $agreement->update(['pdf_path' => $path]);
        } catch (\Throwable $e) {
            // Log and swallow — never break the signing flow.
            Log::error('GenerateAgreementPdfJob failed', [
                'tenant_id' => $this->tenantId,
                'agreement_id' => $this->agreementId,
                'message' => $e->getMessage(),
            ]);
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }
}
