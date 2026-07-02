<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Events\UpgradeRequested;
use App\Modules\SaasCore\Http\Requests\UpgradeRequestRequest;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\UpgradeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Tenant-side plan-upgrade requests ("contact us to upgrade" — no self-service
 * billing yet). TENANT-ADMIN ONLY via the 'requestUpgrade' gate.
 *
 * Behind ['web','tenant','auth:tenant']: current_tenant is bound, so the
 * UpgradeRequest is created tenant-scoped (tenant_id auto-populated by HasTenant).
 */
class UpgradeRequestController extends Controller
{
    public function store(UpgradeRequestRequest $request): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('requestUpgrade');

        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        $upgradeRequest = UpgradeRequest::create([
            'requested_plan_id' => $request->integer('requested_plan_id'),
            // The plan the tenant is on right now (null when they have none).
            'current_plan_id' => $tenant->activePlan()?->id,
            'status' => UpgradeRequest::STATUS_PENDING,
            'notes' => $request->input('notes'),
        ]);

        UpgradeRequested::dispatch($upgradeRequest);

        return back()->with('success', __('common.billing.upgrade_requested'));
    }
}
