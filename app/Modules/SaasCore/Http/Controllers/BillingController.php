<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\UsageService;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant billing portal — TENANT-ADMIN ONLY.
 *
 * Runs behind ['web', 'tenant', 'auth:tenant'], so current_tenant is bound and
 * every count in UsageService is tenant-scoped. Authorization is the model-less
 * 'viewBilling' gate (BillingPolicy@view) via the load-bearing forUser pattern —
 * NOT $this->authorize(), which would resolve the empty web guard.
 *
 * Read-only: tenants CANNOT self-assign a plan (that is a super-admin action, or
 * Stripe self-service in a later session). The only mutation from here is
 * submitting an UpgradeRequest (UpgradeRequestController).
 */
class BillingController extends Controller
{
    public function index(UsageService $usage): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewBilling');

        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        $subscription = $tenant->activeSubscription;
        $plan = $subscription?->plan;

        // Trial days remaining — only while trialing and not lapsed (same rule as
        // the dashboard).
        $trialDaysRemaining = null;
        if ($subscription?->status === Subscription::STATUS_TRIALING
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isFuture()
        ) {
            $trialDaysRemaining = (int) ceil(now()->diffInDays($subscription->trial_ends_at, false));
        }

        // Modules NOT included in the current plan (empty when no plan).
        $enabledModules = $plan?->modules ?? [];
        $disabledModules = array_values(array_diff(Plan::MODULE_KEYS, $enabledModules));

        // Active plans available for upgrade, cheapest first.
        $availablePlans = Plan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_monthly')
            ->get()
            ->map(fn (Plan $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'description' => $p->description,
                'price_monthly' => $p->price_monthly,
                'price_annual' => $p->price_annual,
                'is_free' => $p->is_free,
                'modules' => $p->modules ?? [],
                'limits' => $p->limits ?? [],
                'is_current' => $plan !== null && $p->id === $plan->id,
            ]);

        // Billing history — every subscription this tenant has held, newest first.
        $history = $tenant->subscriptions()->with('plan:id,name')->get()
            ->map(fn (Subscription $sub) => [
                'id' => $sub->id,
                'plan_name' => $sub->plan?->name,
                'status' => $sub->status,
                'billing_cycle' => $sub->billing_cycle,
                'current_period_start' => $sub->current_period_start,
                'current_period_end' => $sub->current_period_end,
                'created_at' => $sub->created_at,
            ]);

        return Inertia::render('Billing/Index', [
            'currentPlan' => $plan === null ? null : [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'price_monthly' => $plan->price_monthly,
                'price_annual' => $plan->price_annual,
                'is_free' => $plan->is_free,
            ],
            'subscription' => $subscription === null ? null : [
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'current_period_end' => $subscription->current_period_end,
            ],
            'trialDaysRemaining' => $trialDaysRemaining,
            'usage' => $usage->getUsage($tenant),
            'disabledModules' => $disabledModules,
            'availablePlans' => $availablePlans,
            'history' => $history,
        ]);
    }
}
