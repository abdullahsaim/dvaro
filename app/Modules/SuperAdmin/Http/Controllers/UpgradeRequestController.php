<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\UpgradeRequest;
use App\Modules\SaasCore\Services\AssignPlanService;
use App\Modules\SuperAdmin\Http\Requests\CompleteUpgradeRequest;
use App\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Upgrade-request queue (super admin panel). supportAccess-gated.
 *
 * UpgradeRequest is tenant-scoped (HasTenant), but there is NO bound tenant in
 * the super admin panel — so every query drops TenantScope, and records are
 * resolved by explicit id (NOT route-model binding, which would trip the scope
 * and throw). Same pattern as SubscriptionManagementController::show.
 */
class UpgradeRequestController extends Controller
{
    public function index(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        // Pending queue — the requests awaiting action, oldest first (FIFO).
        $requests = UpgradeRequest::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('status', UpgradeRequest::STATUS_PENDING)
            ->with(['tenant:id,name,slug', 'requestedPlan:id,name', 'currentPlan:id,name'])
            ->oldest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (UpgradeRequest $req) => [
                'id' => $req->id,
                'tenant_name' => $req->tenant?->name,
                'tenant_slug' => $req->tenant?->slug,
                'requested_plan' => $req->requestedPlan?->name,
                'current_plan' => $req->currentPlan?->name,
                'notes' => $req->notes,
                'created_at' => $req->created_at,
            ]);

        return Inertia::render('SuperAdmin/UpgradeRequests/Index', [
            'requests' => $requests,
        ]);
    }

    /**
     * Mark a pending request as contacted (awaiting the tenant's confirmation).
     */
    public function markContacted(int $id): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        $this->find($id)->update(['status' => UpgradeRequest::STATUS_CONTACTED]);

        return back()->with('success', __('common.superadmin.upgrade_marked_contacted'));
    }

    /**
     * Complete a request: assign the requested plan to the tenant on the chosen
     * cycle (AssignPlanService — cancels old sub, ends trial, promotes tenant),
     * then mark the request completed.
     */
    public function complete(CompleteUpgradeRequest $request, int $id, AssignPlanService $service): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        $upgradeRequest = $this->find($id);
        $tenant = Tenant::findOrFail($upgradeRequest->tenant_id);

        $service->execute(
            $tenant,
            $upgradeRequest->requested_plan_id,
            $request->string('billing_cycle')->toString(),
        );

        $upgradeRequest->update(['status' => UpgradeRequest::STATUS_COMPLETED]);

        return back()->with('success', __('common.superadmin.upgrade_completed'));
    }

    /**
     * Resolve an UpgradeRequest by id in the unbound super admin context.
     */
    private function find(int $id): UpgradeRequest
    {
        return UpgradeRequest::query()
            ->withoutGlobalScope(TenantScope::class)
            ->findOrFail($id);
    }
}
