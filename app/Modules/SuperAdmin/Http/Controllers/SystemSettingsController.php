<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SuperAdmin\Http\Requests\SystemSettingsRequest;
use App\Modules\SuperAdmin\Services\PlatformSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform settings screen for the super admin panel.
 *
 * Owner-level only (platformOwner). Reads/writes go through
 * PlatformSettingsService (cache-first reads; per-key cache bust on write).
 */
class SystemSettingsController extends Controller
{
    public function __construct(
        private readonly PlatformSettingsService $settings,
    ) {}

    public function show(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('platformOwner');

        return Inertia::render('SuperAdmin/Settings/Index', [
            'settings' => $this->settings->all(),
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
        ]);
    }

    public function update(SystemSettingsRequest $request): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('platformOwner');

        $this->settings->set('platform_name', $request->input('platform_name'));
        $this->settings->set('support_email', $request->input('support_email'));
        $this->settings->set('manual_tenant_approval', $request->boolean('manual_tenant_approval'));
        $this->settings->set('free_trial_enabled', $request->boolean('free_trial_enabled'));
        $this->settings->set('free_trial_days', $request->integer('free_trial_days'));
        $this->settings->set('freemium_enabled', $request->boolean('freemium_enabled'));
        $this->settings->set('maintenance_mode', $request->boolean('maintenance_mode'));
        $this->settings->set('default_plan_id', $request->input('default_plan_id'));
        $this->settings->set('max_tenants', $request->input('max_tenants'));

        return back()->with('success', __('common.superadmin.settings_saved'));
    }
}
