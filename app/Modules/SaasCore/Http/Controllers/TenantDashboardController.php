<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Reporting\Services\DashboardService;
use App\Modules\Reporting\Services\ReportCacheService;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant dashboard landing page.
 *
 * Runs behind ['web', 'tenant', 'auth:tenant'], so TenantMiddleware has already
 * bound current_tenant and shared the slim 'tenant' + 'auth' Inertia props.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * ROLE: money (receivables, overdue amounts) is decided HERE, server-side, and
 * a staff member's payload simply does not contain it — never sent and hidden
 * with CSS. DashboardService::seesMoney() is the single rule.
 *
 * POLLING: the operational blocks are partial-reload targets ('operational'),
 * so the front end can refresh just those every 60s (CLAUDE.md: polling, not
 * WebSockets, in v1) without re-running the subscription lookups.
 * ──────────────────────────────────────────────────────────────────────────
 */
class TenantDashboardController extends Controller
{
    public function index(
        ReportingService $reporting,
        ReportCacheService $cache,
        DashboardService $dashboard,
        TenantSettingsService $settings,
    ): Response {
        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        /** @var TenantUser|null $user */
        $user = auth('tenant')->user();
        $seesMoney = $dashboard->seesMoney($user);

        $subscription = $tenant->activeSubscription;

        // Trial days remaining: only meaningful while trialing and not lapsed.
        $trialDaysRemaining = null;
        if ($subscription?->status === Subscription::STATUS_TRIALING
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isFuture()
        ) {
            $trialDaysRemaining = (int) ceil(now()->diffInDays($subscription->trial_ends_at, false));
        }

        $summary = $reporting->tenantDashboardSummary();

        if (! $seesMoney) {
            // Not merely hidden in the UI — removed from the payload.
            unset($summary['outstanding_balance']);
        }

        return Inertia::render('Tenant/Dashboard', [
            'planName' => $subscription?->plan?->name,
            'subscriptionStatus' => $subscription?->status,
            'trialDaysRemaining' => $trialDaysRemaining,
            'summary' => $summary,
            'seesMoney' => $seesMoney,
            // A CLOSURE, not Inertia::lazy(): a lazy prop is skipped on a full
            // page load, which would paint an empty dashboard. A closure is
            // evaluated on first load AND re-evaluated on a partial reload of
            // just this prop — which is what the 60s poll asks for.
            'operational' => fn () => $cache->dashboardOperational($seesMoney),
            'onboardingChecklist' => $this->onboardingChecklist($tenant, $user, $settings),
        ]);
    }

    /**
     * Dismiss the first-run "Getting started" checklist for good — either
     * the admin ticked everything off already, or they just want it gone.
     * Admin-only (same gate as seeing the checklist in the first place).
     */
    public function dismissOnboarding(TenantSettingsService $settings): RedirectResponse
    {
        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        $settings->update($tenant, ['onboarding_checklist_dismissed' => true], 'onboarding');

        return back();
    }

    /**
     * The first-run setup checklist: null once dismissed, once every step is
     * already done, or for anyone but an admin (the steps are all admin-level
     * actions — a staff member can't invite teammates or see billing).
     *
     * Every check is a cheap COUNT — no joins, nothing that needs the report
     * cache — so this costs nothing on the other 99% of dashboard loads once
     * a tenant is established and the checklist is dismissed.
     *
     * @return array<string, mixed>|null
     */
    private function onboardingChecklist(Tenant $tenant, ?TenantUser $user, TenantSettingsService $settings): ?array
    {
        if ($user === null || $user->role !== TenantUser::ROLE_ADMIN) {
            return null;
        }

        if ($settings->get($tenant, 'onboarding_checklist_dismissed') === true) {
            return null;
        }

        $items = [
            ['key' => 'add_vehicle', 'done' => Vehicle::query()->exists(), 'url' => 'fleet/create'],
            ['key' => 'invite_team', 'done' => TenantUser::query()->count() > 1, 'url' => 'settings/staff'],
            ['key' => 'first_agreement', 'done' => Agreement::query()->exists(), 'url' => 'agreements/create'],
            ['key' => 'customize_company', 'done' => filled($settings->get($tenant, 'logo_path')), 'url' => 'settings/company'],
        ];

        if (collect($items)->every(fn (array $item) => $item['done'])) {
            return null;
        }

        return [
            'items' => $items,
            'completedCount' => collect($items)->where('done', true)->count(),
            'totalCount' => count($items),
        ];
    }
}
