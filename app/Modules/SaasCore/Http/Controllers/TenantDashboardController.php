<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Reporting\Services\DashboardService;
use App\Modules\Reporting\Services\ReportCacheService;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
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
        ]);
    }
}
