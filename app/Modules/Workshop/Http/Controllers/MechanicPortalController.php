<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Workshop\Actions\AddPartAction;
use App\Modules\Workshop\Actions\ChangeServiceLogStatusAction;
use App\Modules\Workshop\Actions\CreateServiceLogAction;
use App\Modules\Workshop\DTOs\CreateServiceLogDTO;
use App\Modules\Workshop\Http\Requests\AddPartRequest;
use App\Modules\Workshop\Http\Requests\CreateServiceLogRequest;
use App\Modules\Workshop\Http\Requests\UpdateStatusRequest;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The mechanic-facing workshop portal ('mechanic' guard).
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING:
 * Every action touching a specific service log MUST authorize via:
 *
 *     Gate::forUser(auth('mechanic')->user())->authorize($ability, $log);
 *
 * Do NOT use $this->authorize(): it resolves the DEFAULT (web) guard, which is
 * empty here — mechanics live on the 'mechanic' guard. Using the wrong guard
 * silently runs MechanicPolicy against a null user. Keep the forUser(mechanic)
 * form on EVERY action. Tenant is bound by ResolveTenantForMechanic.
 * ──────────────────────────────────────────────────────────────────────────
 */
class MechanicPortalController extends Controller
{
    public function dashboard(): Response
    {
        $mechanic = auth('mechanic')->user();

        // This mechanic's open jobs (anything not yet completed), newest first.
        $activeJobs = ServiceLog::query()
            ->where('mechanic_id', $mechanic->id)
            ->where('status', '!=', ServiceLog::STATUS_COMPLETED)
            ->with('vehicle:id,make,model,registration_number,status,qr_code_token')
            ->latest()
            ->get();

        $recentCompleted = ServiceLog::query()
            ->where('mechanic_id', $mechanic->id)
            ->where('status', ServiceLog::STATUS_COMPLETED)
            ->with('vehicle:id,make,model,registration_number')
            ->latest('completed_at')
            ->limit(10)
            ->get();

        return Inertia::render('Workshop/Mechanic/Dashboard', [
            'activeJobs' => $activeJobs,
            'recentCompleted' => $recentCompleted,
        ]);
    }

    /**
     * The vehicle service page reached from a QR scan (mechanic.vehicle). The
     * vehicle is resolved by its QR token — tenant-scoped, so a cross-tenant
     * token never resolves (404).
     */
    public function scanResult(string $tenant_slug, string $token): Response
    {
        $vehicle = Vehicle::query()
            ->where('qr_code_token', $token)
            ->firstOrFail();

        $serviceHistory = ServiceLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->with(['mechanic:id,name', 'parts'])
            ->latest()
            ->get();

        return Inertia::render('Workshop/Mechanic/ScanResult', [
            'vehicle' => $vehicle,
            'token' => $token,
            'serviceHistory' => $serviceHistory,
            'statuses' => ServiceLog::STATUSES,
        ]);
    }

    public function createLog(CreateServiceLogRequest $request, CreateServiceLogAction $action): RedirectResponse
    {
        Gate::forUser(auth('mechanic')->user())->authorize('create', ServiceLog::class);

        // Resolve the vehicle from the QR token the page carries — tenant-scoped,
        // so a cross-tenant token is never found (404).
        $vehicle = Vehicle::query()
            ->where('qr_code_token', $request->string('token'))
            ->firstOrFail();

        $action->execute(
            CreateServiceLogDTO::fromRequest($request, $vehicle->id),
            auth('mechanic')->user(),
        );

        return redirect()
            ->route('mechanic.vehicle', [
                'tenant_slug' => app('current_tenant')->slug,
                'token' => $vehicle->qr_code_token,
            ])
            ->with('success', __('common.workshop.log_created'));
    }

    public function updateStatus(
        UpdateStatusRequest $request,
        ServiceLog $log,
        ChangeServiceLogStatusAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('mechanic')->user())->authorize('update', $log);

        $action->execute($log, $request->string('status')->toString());

        return back()->with('success', __('common.workshop.status_changed'));
    }

    public function addPart(AddPartRequest $request, ServiceLog $log, AddPartAction $action): RedirectResponse
    {
        Gate::forUser(auth('mechanic')->user())->authorize('addPart', $log);

        $action->execute(
            $log,
            $request->string('name')->toString(),
            (int) $request->input('quantity'),
            (int) $request->input('unit_cost'),
        );

        return back()->with('success', __('common.workshop.part_added'));
    }
}
