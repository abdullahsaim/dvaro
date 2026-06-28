<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-admin workshop view (read-only). Thin controller: authorize → query →
 * render. Service logs are CREATED and MUTATED only from the mechanic portal;
 * here the tenant simply oversees them.
 *
 * AUTHORIZATION: authorize against the TENANT guard —
 *   Gate::forUser(auth('tenant')->user())->authorize(...)
 * (MechanicPolicy's read abilities accept any Authenticatable.) Never
 * $this->authorize() (empty web guard). Tenant is bound by TenantMiddleware;
 * {log}/{vehicle} bind through TenantScope (cross-tenant id => 404).
 */
class WorkshopController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', ServiceLog::class);

        return $this->renderIndex($request, vehicle: null);
    }

    public function show(ServiceLog $log): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $log);

        $log->load(['vehicle', 'mechanic:id,name,email', 'parts']);

        return Inertia::render('Workshop/Show', [
            'log' => $log,
            'statuses' => ServiceLog::STATUSES,
        ]);
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
            ->paginate(15)
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
