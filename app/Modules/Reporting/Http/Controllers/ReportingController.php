<?php

namespace App\Modules\Reporting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateReportExportJob;
use App\Modules\Reporting\Http\Requests\ExportRequest;
use App\Modules\Reporting\Http\Requests\ReportRequest;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Services\ReportCacheService;
use App\Modules\Reporting\Services\ReportingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reporting & Analytics (tenant app). Thin controller: authorize → read through
 * ReportCacheService (NEVER ReportingService directly, so the DB is only hit on a
 * cache miss) → render. Exports are queued.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING (same rule as Fleet/Customer/Invoice):
 * Every action MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, $target);
 *
 * Do NOT use $this->authorize(): it resolves the DEFAULT (web) guard, which is
 * empty here — tenant users live on the 'tenant' guard, so the policy would run
 * against a null user and silently pass.
 * ──────────────────────────────────────────────────────────────────────────
 */
class ReportingController extends Controller
{
    public function dashboard(ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        return Inertia::render('Reporting/Dashboard', [
            'stats' => $cache->dashboardStats(),
        ]);
    }

    public function revenue(ReportRequest $request, ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        [$from, $to] = $request->range();

        return Inertia::render('Reporting/Revenue', [
            'byPeriod' => $cache->revenueByPeriod($from, $to),
            'byVehicle' => $cache->revenueByVehicle($from, $to),
            'filters' => $this->filters($from, $to),
            'exports' => $this->recentExports('revenue'),
        ]);
    }

    public function fleet(ReportRequest $request, ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        [$from, $to] = $request->range();

        return Inertia::render('Reporting/Fleet', [
            'rows' => $cache->fleetUtilisation($from, $to),
            'filters' => $this->filters($from, $to),
            'exports' => $this->recentExports('fleet'),
        ]);
    }

    public function overdue(ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        return Inertia::render('Reporting/Overdue', [
            'rows' => $cache->overduePayments(),
            'exports' => $this->recentExports('overdue'),
        ]);
    }

    public function workshop(ReportRequest $request, ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        [$from, $to] = $request->range();

        return Inertia::render('Reporting/Workshop', [
            'report' => $cache->workshopPerformance($from, $to),
            'filters' => $this->filters($from, $to),
            'exports' => $this->recentExports('workshop'),
        ]);
    }

    public function customers(ReportRequest $request, ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        [$from, $to] = $request->range();

        return Inertia::render('Reporting/Customers', [
            'rows' => $cache->customerGrowth($from, $to),
            'filters' => $this->filters($from, $to),
        ]);
    }

    public function maintenance(ReportRequest $request, ReportCacheService $cache): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ReportExport::class);

        [$from, $to] = $request->range();

        return Inertia::render('Reporting/Maintenance', [
            'rows' => $cache->maintenanceCosts($from, $to),
            'filters' => $this->filters($from, $to),
        ]);
    }

    /**
     * Queue a report export. Pre-creates the ReportExport (status=pending) so the
     * UI has an id to track immediately; GenerateReportExportJob finalises it.
     */
    public function export(ExportRequest $request): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('export', ReportExport::class);

        [$from, $to] = $request->range();

        $export = ReportExport::create([
            'requested_by' => auth('tenant')->id(),
            'report_type' => $request->string('report_type')->toString(),
            'format' => $request->string('format')->toString(),
            'parameters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ],
            'status' => ReportExport::STATUS_PENDING,
            // Export files expire 24 hours after they are requested.
            'expires_at' => now()->addDay(),
        ]);

        GenerateReportExportJob::dispatch($export->id, $export->tenant_id);

        return back()->with('success', __('common.reporting.export_queued'));
    }

    /**
     * Serve a generated export via a short-lived signed S3 URL (15 min). Only the
     * owning tenant, only when ready and not expired. {export} binds through
     * TenantScope, so a cross-tenant id 404s before this runs.
     */
    public function downloadExport(ReportExport $export): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('download', $export);

        abort_unless($export->isReady() && $export->file_path !== null, 404);
        abort_if($export->isExpired(), 410);

        return redirect()->away(
            Storage::disk('s3')->temporaryUrl($export->file_path, now()->addMinutes(15)),
        );
    }

    /**
     * The tenant's most recent exports of a given type, for the page's
     * download list. Tenant-scoped via HasTenant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function recentExports(string $reportType): array
    {
        return ReportExport::query()
            ->where('report_type', $reportType)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ReportExport $export) => [
                'id' => $export->id,
                'format' => $export->format,
                'status' => $export->status,
                'created_at' => $export->created_at?->toIso8601String(),
                'downloadable' => $export->isReady() && ! $export->isExpired(),
            ])
            ->all();
    }

    /** @return array{from: string, to: string} */
    private function filters(\Carbon\Carbon $from, \Carbon\Carbon $to): array
    {
        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }
}
