<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin landing dashboard — platform-wide KPIs.
 *
 * Runs behind 'superadmin.auth'. There is NO bound tenant here, so any query on
 * a tenant-scoped model (Subscription) must drop TenantScope explicitly. Tenant
 * and Plan are not tenant-scoped and are queried normally.
 */
class SuperAdminDashboardController extends Controller
{
    public function index(): Response
    {
        $totalTenants = Tenant::query()->count();
        $activeTenants = Tenant::query()->where('status', Tenant::STATUS_ACTIVE)->count();
        $trialTenants = Tenant::query()->where('status', Tenant::STATUS_TRIAL)->count();

        $newThisMonth = Tenant::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        // Active recurring revenue (MRR-style, in cents): for each access-
        // granting subscription, the plan price for its billing cycle. Not
        // historical paid revenue — there is no platform payment ledger yet.
        $recurringRevenue = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [Subscription::STATUS_ACTIVE, Subscription::STATUS_TRIALING])
            ->with('plan:id,price_monthly,price_annual')
            ->get()
            ->sum(function (Subscription $sub): int {
                if ($sub->plan === null) {
                    return 0;
                }

                return $sub->billing_cycle === Subscription::BILLING_ANNUAL
                    ? (int) $sub->plan->price_annual
                    : (int) $sub->plan->price_monthly;
            });

        // Recent signups for the dashboard list.
        $recentTenants = Tenant::query()
            ->with('activeSubscription.plan:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'plan_name' => $tenant->activeSubscription?->plan?->name,
                'created_at' => $tenant->created_at,
            ]);

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => [
                'total_tenants' => $totalTenants,
                'active_tenants' => $activeTenants,
                'trial_tenants' => $trialTenants,
                'new_this_month' => $newThisMonth,
                'recurring_revenue' => $recurringRevenue,
            ],
            'recentTenants' => $recentTenants,
        ]);
    }
}
