<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SuperAdmin\Http\Requests\SystemSettingsRequest;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use App\Modules\SuperAdmin\Services\PlatformSettingsService;
use App\Services\PlatformActivityLogger;
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
        private readonly PlatformActivityLogger $activity,
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

        $keys = [
            'platform_name', 'support_email', 'manual_tenant_approval', 'free_trial_enabled',
            'free_trial_days', 'freemium_enabled', 'maintenance_mode', 'default_plan_id', 'max_tenants',
        ];
        $before = collect($keys)->mapWithKeys(fn (string $k) => [$k => $this->settings->get($k)])->all();

        $this->settings->set('platform_name', $request->input('platform_name'));
        $this->settings->set('support_email', $request->input('support_email'));
        $this->settings->set('manual_tenant_approval', $request->boolean('manual_tenant_approval'));
        $this->settings->set('free_trial_enabled', $request->boolean('free_trial_enabled'));
        $this->settings->set('free_trial_days', $request->integer('free_trial_days'));
        $this->settings->set('freemium_enabled', $request->boolean('freemium_enabled'));
        $this->settings->set('maintenance_mode', $request->boolean('maintenance_mode'));
        $this->settings->set('default_plan_id', $request->input('default_plan_id'));
        $this->settings->set('max_tenants', $request->input('max_tenants'));

        $after = collect($keys)->mapWithKeys(fn (string $k) => [$k => $this->settings->get($k)])->all();
        $this->activity->log(
            'platform_settings.updated',
            PlatformActivityLog::SUBJECT_SETTINGS,
            old: $before,
            new: $after,
        );

        return back()->with('success', __('common.superadmin.settings_saved'));
    }
}
