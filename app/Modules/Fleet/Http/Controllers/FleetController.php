<?php

namespace App\Modules\Fleet\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesForUser;
use App\Http\Controllers\Controller;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Services\ExpenseReportService;
use App\Modules\Fleet\Actions\ChangeVehicleStatusAction;
use App\Modules\Fleet\Actions\CreateVehicleAction;
use App\Modules\Fleet\Actions\RecordOdometerReadingAction;
use App\Modules\Fleet\Actions\UpdateVehicleAction;
use App\Modules\Fleet\DTOs\CreateVehicleDTO;
use App\Modules\Fleet\DTOs\UpdateVehicleDTO;
use App\Modules\Fleet\Http\Requests\ChangeStatusRequest;
use App\Modules\Fleet\Http\Requests\RecordOdometerRequest;
use App\Modules\Fleet\Http\Requests\StoreVehicleRequest;
use App\Modules\Fleet\Http\Requests\UpdateVehicleRequest;
use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\Workshop\Actions\GenerateVehicleQrAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Fleet vehicle management (tenant app). Thin controller: validate → Action →
 * redirect/render. All business logic lives in Actions.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING:
 * Every action that touches a SPECIFIC vehicle (show, edit, update, destroy,
 * changeStatus, recordOdometer) MUST authorize via:
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
    use PaginatesForUser;

    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Vehicle::class);

        // Only filter on a known status; anything else means "All".
        $status = $request->query('status');
        if (! in_array($status, Vehicle::STATUSES, true)) {
            $status = null;
        }

        $expiring = $request->boolean('expiring');

        // Sort: whitelisted date columns only; empty dates always sort last.
        $sort = in_array($request->query('sort'), Vehicle::SORTABLE_DATES, true)
            ? $request->query('sort')
            : null;
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        $vehicles = Vehicle::query()
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($expiring, fn ($query) => $query->expiringSoon())
            ->when(
                $sort,
                fn ($query) => $query->orderByRaw("{$sort} {$direction} NULLS LAST")->orderBy('id'),
                fn ($query) => $query->latest(),
            )
            ->paginate($this->perPage(15))
            ->withQueryString();

        $vehicles->through(fn (Vehicle $vehicle) => tap($vehicle, function (Vehicle $v) {
            $v->setAttribute('registration_state', $v->expiryState('registration_expiry'));
            $v->setAttribute('service_state', $v->serviceState());
        }));

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
            'expiringCount' => Vehicle::query()->expiringSoon()->count(),
            'filters' => [
                'expiring' => $expiring,
                'sort' => $sort,
                'direction' => $direction,
            ],
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

        $action->execute(CreateVehicleDTO::fromRequest($request), auth('tenant')->id());

        return redirect()
            ->route('tenant.fleet.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.fleet.created'));
    }

    public function show(
        Vehicle $vehicle,
        ExpenseReportService $expenseReports,
        ReportingService $reporting,
    ): Response {
        Gate::forUser(auth('tenant')->user())->authorize('view', $vehicle);

        $fy = $reporting->australianFY();

        // Recent workshop history for the service-history section.
        $serviceLogs = $vehicle->serviceLogs()
            ->with('mechanic:id,name')
            ->latest()
            ->limit(10)
            ->get();

        // Append-only odometer history (newest first).
        $odometerReadings = $vehicle->odometerReadings()
            ->latest('recorded_at')
            ->latest('id')
            ->limit(10)
            ->get(['id', 'reading', 'source', 'recorded_at']);

        return Inertia::render('Fleet/Show', [
            'vehicle' => $vehicle,
            'statuses' => Vehicle::STATUSES,
            // QR is rendered via the stream endpoint only when a token exists.
            'hasQr' => $vehicle->qr_code_token !== null,
            'serviceLogs' => $serviceLogs,
            'odometerReadings' => $odometerReadings,
            'serviceState' => $vehicle->serviceState(),
            // Vehicle-linked expenses (active only): latest 5 + this-FY total.
            'vehicleExpenses' => Expense::query()
                ->active()
                ->where('vehicle_id', $vehicle->id)
                ->with('category:id,name')
                ->orderByDesc('expense_date')
                ->orderByDesc('id')
                ->limit(5)
                ->get(['id', 'expense_category_id', 'expense_date', 'description', 'amount_total']),
            'vehicleExpensesFy' => $expenseReports->summary($fy['from'], $fy['to'], null, $vehicle->id)['totals']['total'],
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

    /**
     * Staff-entered odometer reading. Goes through RecordOdometerReadingAction
     * (append-only history; a backwards reading is a validation error).
     */
    public function recordOdometer(
        RecordOdometerRequest $request,
        Vehicle $vehicle,
        RecordOdometerReadingAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('update', $vehicle);

        $action->execute(
            $vehicle,
            $request->integer('reading'),
            OdometerReading::SOURCE_MANUAL,
            OdometerReading::ACTOR_TENANT_USER,
            auth('tenant')->id(),
        );

        return back()->with('success', __('common.fleet.odometer_recorded'));
    }

    /**
     * Generate (or regenerate) the vehicle's QR code. The token is deterministic,
     * so regenerating is idempotent and never invalidates a printed sticker.
     */
    public function generateQr(Vehicle $vehicle, GenerateVehicleQrAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('update', $vehicle);

        $action->execute($vehicle);

        return back()->with('success', __('common.fleet.qr_generated'));
    }

    /**
     * Stream the vehicle's stored QR (SVG) from the public disk for inline
     * display. Admin-only (behind auth:tenant); 404 until a QR has been
     * generated. The QR SVG is also directly reachable at /storage/{qrPath}
     * (non-sensitive) — this route just keeps the existing auth-checked stream.
     */
    public function qr(Vehicle $vehicle): StreamedResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $vehicle);

        abort_if($vehicle->qr_code_token === null, 404);

        return Storage::disk('public')->response(
            GenerateVehicleQrAction::qrPath($vehicle),
            'qr.svg',
            ['Content-Type' => 'image/svg+xml'],
        );
    }
}
