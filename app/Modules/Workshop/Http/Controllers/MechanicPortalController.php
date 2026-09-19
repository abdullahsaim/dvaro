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
use App\Modules\Workshop\Services\VehicleLookupService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
     * QR entry point (mechanic.vehicle). Resolves the vehicle by its QR token —
     * tenant-scoped, so a cross-tenant token never resolves (404) — then hands
     * off to the id-based vehicle page, so QR and plate search share ONE page.
     * Kept at this URL so printed QR stickers + post-login redirects still work.
     */
    public function scanResult(string $token, VehicleLookupService $lookup): RedirectResponse
    {
        $vehicle = $lookup->findByToken($token);

        return redirect()->route('mechanic.vehicles.show', [
            'tenant_slug' => app('current_tenant')->slug,
            'vehicle' => $vehicle->id,
        ]);
    }

    /**
     * Manual lookup by number plate (for vehicles without a QR sticker, or a
     * damaged one). One match → straight to the vehicle page; otherwise a
     * results list (possibly empty).
     */
    public function searchVehicles(Request $request, VehicleLookupService $lookup): Response|RedirectResponse
    {
        $plate = trim((string) $request->query('plate', ''));
        $results = $lookup->searchByPlate($plate);

        if ($results->count() === 1) {
            return redirect()->route('mechanic.vehicles.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'vehicle' => $results->first()->id,
            ]);
        }

        return Inertia::render('Workshop/Mechanic/VehicleSearch', [
            'plate' => $plate,
            'results' => $results,
            'tooShort' => strlen(VehicleLookupService::normalizePlate($plate)) < VehicleLookupService::MIN_QUERY_LENGTH,
        ]);
    }

    /**
     * The vehicle service page. {vehicle} binds through TenantScope, so another
     * tenant's vehicle id is a 404.
     */
    public function showVehicle(Vehicle $vehicle): Response
    {
        $serviceHistory = ServiceLog::query()
            ->where('vehicle_id', $vehicle->id)
            ->with(['mechanic:id,name', 'parts'])
            ->latest()
            ->get();

        return Inertia::render('Workshop/Mechanic/ScanResult', [
            'vehicle' => $vehicle,
            'serviceHistory' => $serviceHistory,
            'statuses' => ServiceLog::STATUSES,
        ]);
    }

    public function createLog(
        CreateServiceLogRequest $request,
        CreateServiceLogAction $action,
        VehicleLookupService $lookup,
    ): RedirectResponse {
        Gate::forUser(auth('mechanic')->user())->authorize('create', ServiceLog::class);

        // By vehicle_id (current page) or QR token (pages opened before the
        // id-based route) — tenant-scoped either way, cross-tenant => 404.
        $vehicle = $lookup->resolveForLog(
            $request->filled('vehicle_id') ? $request->integer('vehicle_id') : null,
            $request->filled('token') ? $request->string('token')->toString() : null,
        );

        $action->execute(
            CreateServiceLogDTO::fromRequest($request, $vehicle->id),
            auth('mechanic')->user(),
        );

        return redirect()
            ->route('mechanic.vehicles.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'vehicle' => $vehicle->id,
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
