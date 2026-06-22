<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Actions\ChangeVehicleStatusAction;
use App\Modules\Fleet\Actions\CreateVehicleAction;
use App\Modules\Fleet\Actions\UpdateVehicleAction;
use App\Modules\Fleet\DTOs\CreateVehicleDTO;
use App\Modules\Fleet\DTOs\UpdateVehicleDTO;
use App\Modules\Fleet\Http\Requests\ChangeStatusRequest;
use App\Modules\Fleet\Http\Requests\StoreVehicleRequest;
use App\Modules\Fleet\Http\Requests\UpdateVehicleRequest;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Fleet vehicle management (tenant app). Thin controller: validate → Action →
 * redirect/render. All business logic lives in Actions.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING:
 * Every action that touches a SPECIFIC vehicle (show, edit, update, destroy,
 * changeStatus) MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, $vehicle);
 *
 * (Laravel ships no global gate() helper — use the Gate facade. The
 * forUser(auth('tenant')->user()) part is what matters.) Do NOT use
 * $this->authorize(): it resolves the user from the DEFAULT (web) guard, which
 * is empty here — tenant users live on the 'tenant' guard. Falling back to
 * $this->authorize() on even one action silently runs the policy check against
 * the wrong (null) user, so VehiclePolicy never actually runs against the
 * tenant user. Keep the forUser(auth('tenant')->user()) form on EVERY one.
 * ──────────────────────────────────────────────────────────────────────────
 */
class FleetController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Vehicle::class);

        // Only filter on a known status; anything else means "All".
        $status = $request->query('status');
        if (! in_array($status, Vehicle::STATUSES, true)) {
            $status = null;
        }

        $vehicles = Vehicle::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Per-status counts for the filter tabs (tenant-scoped). 'all' is the total.
        $counts = Vehicle::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = ['all' => (int) $counts->sum()];
        foreach (Vehicle::STATUSES as $s) {
            $statusCounts[$s] = (int) ($counts[$s] ?? 0);
        }

        return Inertia::render('Fleet/Index', [
            'vehicles' => $vehicles,
            'statuses' => Vehicle::STATUSES,
            'statusCounts' => $statusCounts,
            'activeStatus' => $status,
        ]);
    }

    public function create(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Vehicle::class);

        return Inertia::render('Fleet/Create', [
            'statuses' => Vehicle::STATUSES,
        ]);
    }

    public function store(StoreVehicleRequest $request, CreateVehicleAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Vehicle::class);

        $action->execute(CreateVehicleDTO::fromRequest($request));

        return redirect()
            ->route('tenant.fleet.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.fleet.created'));
    }

    public function show(Vehicle $vehicle): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $vehicle);

        return Inertia::render('Fleet/Show', [
            'vehicle' => $vehicle,
            'statuses' => Vehicle::STATUSES,
        ]);
    }

    public function edit(Vehicle $vehicle): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('update', $vehicle);

        return Inertia::render('Fleet/Edit', [
            'vehicle' => $vehicle,
            'statuses' => Vehicle::STATUSES,
        ]);
    }

    public function update(
        UpdateVehicleRequest $request,
        Vehicle $vehicle,
        UpdateVehicleAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('update', $vehicle);

        $action->execute($vehicle, UpdateVehicleDTO::fromRequest($request));

        return redirect()
            ->route('tenant.fleet.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'vehicle' => $vehicle->id,
            ])
            ->with('success', __('common.fleet.updated'));
    }

    public function destroy(Vehicle $vehicle): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('delete', $vehicle);

        // Soft delete only — no hard deletes anywhere in this codebase.
        $vehicle->delete();

        return redirect()
            ->route('tenant.fleet.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.fleet.deleted'));
    }

    public function changeStatus(
        ChangeStatusRequest $request,
        Vehicle $vehicle,
        ChangeVehicleStatusAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('changeStatus', $vehicle);

        // Status mutates ONLY through ChangeVehicleStatusAction (validates +
        // fires VehicleStatusChanged). Request already guarantees a valid status.
        $action->execute($vehicle, $request->string('status')->toString());

        return back()->with('success', __('common.fleet.status_changed'));
    }
}
