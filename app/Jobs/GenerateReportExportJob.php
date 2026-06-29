<?php

namespace App\Jobs;

use App\Modules\Reporting\Events\ReportExportReady;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Services\ExportService;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\SaasCore\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Generates a report file (PDF or Excel) off the request thread and stores it on
 * S3 (CLAUDE.md: exports are ALWAYS queued, never synchronous). Dispatched on
 * the dedicated 'exports' queue.
 *
 * The ReportExport row is pre-created (status=pending) by the controller, so the
 * UI has an id to track immediately; this job finalises it to ready/failed.
 *
 * Runs with NO bound tenant (queue worker context). It binds current_tenant for
 * the duration of the job — so every reporting query resolves through its global
 * scope — and forgets it afterwards so nothing leaks to the next job.
 */
class GenerateReportExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(
        public readonly int $reportExportId,
        public readonly int $tenantId,
    ) {
        $this->onQueue('exports');
    }

    public function handle(ReportingService $reporting, ExportService $exporter): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            Log::warning('GenerateReportExportJob: tenant not found', [
                'tenant_id' => $this->tenantId,
                'report_export_id' => $this->reportExportId,
            ]);

            return;
        }

        app()->instance('current_tenant', $tenant);

        /** @var ReportExport|null $export */
        $export = ReportExport::find($this->reportExportId);

        if ($export === null) {
            Log::warning('GenerateReportExportJob: export record not found', [
                'tenant_id' => $this->tenantId,
                'report_export_id' => $this->reportExportId,
            ]);

            app()->forgetInstance('current_tenant');

            return;
        }

        try {
            [$from, $to] = $this->range($export->parameters ?? []);
            $data = $this->buildData($reporting, $export->report_type, $from, $to);
            $title = $this->title($export->report_type, $from, $to);

            $path = $export->format === ReportExport::FORMAT_EXCEL
                ? $exporter->exportExcel($export->report_type, $data, $title)
                : $exporter->exportPdf($export->report_type, $data, $title);

            $export->update([
                'status' => ReportExport::STATUS_READY,
                'file_path' => $path,
            ]);

            ReportExportReady::dispatch($export);
        } catch (\Throwable $e) {
            Log::error('GenerateReportExportJob failed', [
                'tenant_id' => $this->tenantId,
                'report_export_id' => $this->reportExportId,
                'message' => $e->getMessage(),
            ]);

            $export->update(['status' => ReportExport::STATUS_FAILED]);
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }

    /**
     * If the job is permanently dropped before handle() (e.g. worker crash),
     * mark the export failed so the UI never waits forever.
     */
    public function failed(\Throwable $exception): void
    {
        app()->instance('current_tenant', Tenant::find($this->tenantId));

        ReportExport::where('id', $this->reportExportId)
            ->update(['status' => ReportExport::STATUS_FAILED]);

        app()->forgetInstance('current_tenant');
    }

    /**
     * Assemble the report data the exporter expects for $reportType.
     *
     * @return array<string, mixed>
     */
    private function buildData(ReportingService $reporting, string $reportType, Carbon $from, Carbon $to): array
    {
        return match ($reportType) {
            'revenue' => [
                'by_period' => $reporting->revenueByPeriod($from, $to),
                'by_vehicle' => $reporting->revenueByVehicle($from, $to),
            ],
            'fleet' => ['rows' => $reporting->fleetUtilisation($from, $to)],
            'overdue' => ['rows' => $reporting->overduePayments()],
            'workshop' => $reporting->workshopPerformance($from, $to),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array{0: Carbon, 1: Carbon}
     */
    private function range(array $parameters): array
    {
        $from = isset($parameters['from'])
            ? Carbon::parse($parameters['from'])->startOfDay()
            : Carbon::now('Australia/Sydney')->startOfYear();

        $to = isset($parameters['to'])
            ? Carbon::parse($parameters['to'])->endOfDay()
            : Carbon::now('Australia/Sydney')->endOfDay();

        return [$from, $to];
    }

    private function title(string $reportType, Carbon $from, Carbon $to): string
    {
        $label = ucfirst($reportType);
        $range = $from->format('d/m/Y').' – '.$to->format('d/m/Y');

        return "{$label} report ({$range})";
    }
}
