<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAgreementPdfJob;
use App\Jobs\GenerateInvoicePdfJob;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Re-queues PDFs for agreements and invoices that never got one.
 *
 * WHY THIS EXISTS: PDF generation is queued (CLAUDE.md — dompdf is far too slow
 * to block a request). If no queue worker is running when an agreement is
 * signed, the job is never consumed and the record says "the PDF is being
 * generated" forever. The jobs are gone; only a fresh dispatch recovers them.
 *
 * Run it after a deploy, after any period with the worker stopped, or whenever
 * someone reports a missing document. Safe to re-run: it only touches records
 * whose PDF is genuinely absent, so an existing signed document is never
 * re-rendered (a company that has since rebranded must not get a
 * different-looking copy of a document someone already signed).
 *
 * Runs with NO bound tenant (CLI context) and binds each tenant before any
 * scoped query — a bare Agreement::query() would throw TenantNotResolvedException.
 * Per-tenant failures are logged and skipped so one bad row cannot abort the run.
 */
class GenerateMissingPdfsCommand extends Command
{
    protected $signature = 'pdfs:generate-missing
        {--tenant= : Limit to one tenant slug}
        {--type=all : agreements | invoices | all}
        {--dry-run : List what would be queued without dispatching}';

    protected $description = 'Queue PDFs for agreements and invoices that are missing one (all tenants).';

    public function handle(): int
    {
        $type = (string) $this->option('type');

        if (! in_array($type, ['all', 'agreements', 'invoices'], true)) {
            $this->error('--type must be agreements, invoices or all.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $slug = $this->option('tenant');

        $tenants = Tenant::query()
            ->when($slug, fn ($q) => $q->where('slug', $slug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error($slug ? "No tenant with slug [{$slug}]." : 'No tenants found.');

            return self::FAILURE;
        }

        $queued = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant', $tenant);

            try {
                if ($type !== 'invoices') {
                    $queued += $this->sweepAgreements($tenant, $dryRun);
                }

                if ($type !== 'agreements') {
                    $queued += $this->sweepInvoices($tenant, $dryRun);
                }
            } catch (Throwable $e) {
                $this->error("  {$tenant->slug}: {$e->getMessage()}");
            } finally {
                app()->forgetInstance('current_tenant');
            }
        }

        $this->newLine();
        $this->info($dryRun
            ? "{$queued} document(s) would be queued. Re-run without --dry-run to do it."
            : "{$queued} document(s) queued. They need a worker: php artisan queue:work redis --queue=pdf");

        return self::SUCCESS;
    }

    private function sweepAgreements(Tenant $tenant, bool $dryRun): int
    {
        $agreements = Agreement::query()
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'version', 'pdf_path'])
            ->filter(fn (Agreement $a) => $this->missing($a->pdf_path));

        foreach ($agreements as $agreement) {
            $this->line("  {$tenant->slug}: agreement #{$agreement->id} v{$agreement->version}");

            if (! $dryRun) {
                GenerateAgreementPdfJob::dispatch((int) $agreement->id, (int) $tenant->id);
            }
        }

        return $agreements->count();
    }

    private function sweepInvoices(Tenant $tenant, bool $dryRun): int
    {
        $invoices = Invoice::query()
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'pdf_path'])
            ->filter(fn (Invoice $i) => $this->missing($i->pdf_path));

        foreach ($invoices as $invoice) {
            $this->line("  {$tenant->slug}: invoice #{$invoice->id}");

            if (! $dryRun) {
                GenerateInvoicePdfJob::dispatch((int) $invoice->id, (int) $tenant->id);
            }
        }

        return $invoices->count();
    }

    /**
     * Missing means no path OR a path whose file is gone — a record can point
     * at a file that was deleted from the disk, and that reads to the user
     * exactly like a PDF that never generated.
     */
    private function missing(?string $path): bool
    {
        if ($path === null || $path === '') {
            return true;
        }

        return ! Storage::disk(config('filesystems.default'))->exists($path);
    }
}
