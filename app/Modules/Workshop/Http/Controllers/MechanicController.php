<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workshop\Actions\CreateMechanicAction;
use App\Modules\Workshop\Actions\UpdateMechanicAction;
use App\Modules\Workshop\DTOs\CreateMechanicDTO;
use App\Modules\Workshop\DTOs\UpdateMechanicDTO;
use App\Modules\Workshop\Http\Requests\StoreMechanicRequest;
use App\Modules\Workshop\Http\Requests\UpdateMechanicRequest;
use App\Modules\Workshop\Models\Mechanic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mechanic account management (tenant app — tenant_admin only). Thin controller:
 * validate → Action → redirect/render. All logic lives in Actions/DTOs.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING:
 * Every action MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, ...);
 *
 * against ManageMechanicPolicy (tenant_admin only). Do NOT use $this->
 * authorize(): it resolves the empty default (web) guard, silently running the
 * policy against a null user. Keep the forUser(auth('tenant')->user()) form on
 * EVERY action. Tenant is bound by TenantMiddleware. {mechanic} binds through
 * TenantScope (cross-tenant id => 404).
 * ──────────────────────────────────────────────────────────────────────────
 */
class MechanicController extends Controller
{
    public function index(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Mechanic::class);

        $mechanics = Mechanic::query()
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Mechanic/Index', [
            'mechanics' => $mechanics,
        ]);
    }

    public function create(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Mechanic::class);

        return Inertia::render('Mechanic/Create');
    }

    public function store(StoreMechanicRequest $request, CreateMechanicAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Mechanic::class);

        $action->execute(CreateMechanicDTO::fromRequest($request));

        return redirect()
            ->route('tenant.mechanics.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.mechanic.created'));
    }

    public function edit(Mechanic $mechanic): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('update', $mechanic);

        return Inertia::render('Mechanic/Edit', [
            'mechanic' => $mechanic,
        ]);
    }

    public function update(
        UpdateMechanicRequest $request,
        Mechanic $mechanic,
        UpdateMechanicAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('update', $mechanic);

        $action->execute($mechanic, UpdateMechanicDTO::fromRequest($request));

        return redirect()
            ->route('tenant.mechanics.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.mechanic.updated'));
    }

    public function destroy(Mechanic $mechanic): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('delete', $mechanic);

        // Soft delete only — no hard deletes anywhere in this codebase.
        $mechanic->delete();

        return redirect()
            ->route('tenant.mechanics.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.mechanic.deleted'));
    }
}
