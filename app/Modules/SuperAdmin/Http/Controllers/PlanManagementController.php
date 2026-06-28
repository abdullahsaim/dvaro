<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SuperAdmin\Http\Requests\StorePlanRequest;
use App\Modules\SuperAdmin\Http\Requests\UpdatePlanRequest;
use App\Modules\SuperAdmin\Services\PlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan management for the super admin panel.
 *
 * Plans are platform-wide (not tenant-scoped) and CAN be edited. Deletion is
 * deliberately not exposed (no destroy route): a plan referenced by any
 * subscription must not be removed (plans→subscriptions FK is restrictOnDelete).
 * Toggle is_active instead of deleting.
 *
 * Authorize via Gate::forUser(auth('superadmin')->user())->authorize('billingAccess').
 */
class PlanManagementController extends Controller
{
    public function index(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        $plans = Plan::query()
            ->withCount('subscriptions')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        return Inertia::render('SuperAdmin/Plans/Create', [
            'moduleKeys' => Plan::MODULE_KEYS,
            'limitKeys' => Plan::LIMIT_KEYS,
        ]);
    }

    public function store(StorePlanRequest $request, PlanService $service): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        $service->create($this->coerceBooleans($request));

        return redirect()->route('superadmin.plans.index')
            ->with('success', __('common.superadmin.plan_created'));
    }

    public function edit(Plan $plan): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
            'moduleKeys' => Plan::MODULE_KEYS,
            'limitKeys' => Plan::LIMIT_KEYS,
        ]);
    }

    public function update(UpdatePlanRequest $request, Plan $plan, PlanService $service): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        $service->update($plan, $this->coerceBooleans($request));

        return redirect()->route('superadmin.plans.index')
            ->with('success', __('common.superadmin.plan_updated'));
    }

    public function toggle(Plan $plan, PlanService $service): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        $service->toggle($plan);

        return back()->with('success', __('common.superadmin.plan_toggled'));
    }

    /**
     * validated() omits unchecked checkboxes; resolve the booleans explicitly so
     * the stored values are always definite. (Mapping only — no business logic.)
     *
     * @return array<string, mixed>
     */
    private function coerceBooleans(StorePlanRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active');
        $data['is_free'] = $request->boolean('is_free');

        return $data;
    }
}
