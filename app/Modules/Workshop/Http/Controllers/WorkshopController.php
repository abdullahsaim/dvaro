<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesForUser;
use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Workshop\Actions\ScheduleServiceAction;
use App\Modules\Workshop\Actions\UploadServiceLogDocumentAction;
use App\Modules\Workshop\DTOs\ScheduleServiceDTO;
use App\Modules\Workshop\Http\Requests\ScheduleServiceRequest;
use App\Modules\Workshop\Http\Requests\UploadServiceLogDocumentRequest;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use App\Modules\Workshop\Models\ServiceLogDocument;
use App\Services\FileUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-admin workshop view. Mostly read-only — service logs are CREATED and
 * MUTATED from the mechanic portal — with two admin-initiated exceptions:
 * booking a future appointment (schedule/storeSchedule) and attaching a
 * document from the desktop (uploadDocument). Thin controller: authorize →
 * query/delegate → render.
 *
 * AUTHORIZATION: authorize against the TENANT guard —
 *   Gate::forUser(auth('tenant')->user())->authorize(...)
 * (MechanicPolicy's read/schedule abilities accept any Authenticatable.) Never
 * $this->authorize() (empty web guard). Tenant is bound by TenantMiddleware;
 * {log}/{vehicle} bind through TenantScope (cross-tenant id => 404).
 */
class WorkshopController extends Controller
{
    use PaginatesForUser;

    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ServiceLog::class);

        return $this->renderIndex($request, vehicle: null);
    }

    public function show(ServiceLog $log): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $log);

        $log->load(['vehicle', 'mechanic:id,name,email', 'parts', 'documents']);

        return Inertia::render('Workshop/Show', [
            'log' => $log,
            'statuses' => ServiceLog::STATUSES,
        ]);
    }

    /**
     * Document download — same signed-URL pattern as every other sensitive
     * file in the app (customer documents, expense receipts). {log}/{document}
     * both bind through TenantScope, so a cross-tenant pairing 404s before
     * this runs; the explicit service_log_id check below also rejects a
     * document that belongs to a DIFFERENT log within the same tenant.
     */
    public function downloadDocument(ServiceLog $log, ServiceLogDocument $document, FileUrlService $fileUrls): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $log);

        abort_unless((int) $document->service_log_id === (int) $log->id, 404);

        return redirect()->away($fileUrls->temporaryUrl($document->path));
    }

    public function uploadDocument(
        UploadServiceLogDocumentRequest $request,
        ServiceLog $log,
        UploadServiceLogDocumentAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('view', $log);

        $action->execute(
            $log,
            $request->file('file'),
            ServiceLogDocument::UPLOADED_BY_TENANT_USER,
            (int) auth('tenant')->id(),
        );

        return back()->with('success', __('common.workshop.document_added'));
    }

    public function deleteDocument(ServiceLog $log, ServiceLogDocument $document, UploadServiceLogDocumentAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $log);

        abort_unless((int) $document->service_log_id === (int) $log->id, 404);

        $action->delete($document);

        return back()->with('success', __('common.workshop.document_removed'));
    }

    /**
     * Book a future appointment — the form.
     */
    public function scheduleForm(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('schedule', ServiceLog::class);

        return Inertia::render('Workshop/Schedule', [
            'vehicles' => Vehicle::query()
                ->orderBy('make')->orderBy('model')
                ->get(['id', 'make', 'model', 'registration_number']),
            'mechanics' => Mechanic::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function storeSchedule(ScheduleServiceRequest $request, ScheduleServiceAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('schedule', ServiceLog::class);

        // Captured BEFORE the action runs — defensive: ServiceScheduled has no
        // listener today, but if one is ever added, a queued listener's
        // forgetTenant() would otherwise clear this binding first. Same
        // footgun documented on PublicAgreementSigningController::submit().
        $tenantSlug = app('current_tenant')->slug;

        $log = $action->execute(ScheduleServiceDTO::fromRequest($request));

        return redirect()
            ->route('tenant.workshop.show', ['tenant_slug' => $tenantSlug, 'log' => $log->id])
            ->with('success', __('common.workshop.service_scheduled'));
    }

    /**
     * All service logs for one vehicle — the Index page preset to that vehicle.
     */
    public function vehicleHistory(Request $request, Vehicle $vehicle): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ServiceLog::class);

        return $this->renderIndex($request, vehicle: $vehicle);
    }

    /**
     * Shared Index render: status + vehicle filters, per-status counts, the
     * vehicle dropdown. When $vehicle is given it pins the vehicle filter.
     */
    private function renderIndex(Request $request, ?Vehicle $vehicle): Response
    {
        $status = $request->query('status');
        if (! in_array($status, ServiceLog::STATUSES, true)) {
            $status = null;
        }

        // Vehicle filter: an explicit page route ($vehicle) wins; otherwise the
        // ?vehicle_id query param (validated below).
        $vehicleId = $vehicle?->id;
        if ($vehicleId === null && $request->filled('vehicle_id')) {
            $vehicleId = (int) $request->query('vehicle_id');
        }

        $logs = ServiceLog::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->with(['vehicle:id,make,model,registration_number', 'mechanic:id,name'])
            ->latest()
            ->paginate($this->perPage(15))
            ->withQueryString();

        $counts = ServiceLog::query()
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId))
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = ['all' => (int) $counts->sum()];
        foreach (ServiceLog::STATUSES as $s) {
            $statusCounts[$s] = (int) ($counts[$s] ?? 0);
        }

        return Inertia::render('Workshop/Index', [
            'logs' => $logs,
            'statuses' => ServiceLog::STATUSES,
            'statusCounts' => $statusCounts,
            'activeStatus' => $status,
            'activeVehicleId' => $vehicleId,
            'vehicles' => Vehicle::query()
                ->orderBy('make')->orderBy('model')
                ->get(['id', 'make', 'model', 'registration_number']),
        ]);
    }
}
