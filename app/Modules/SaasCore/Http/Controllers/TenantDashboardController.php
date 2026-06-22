<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The tenant dashboard landing page.
 *
 * Runs behind ['web', 'tenant', 'auth:tenant'], so TenantMiddleware has already
 * bound current_tenant and shared the slim 'tenant' + 'auth' Inertia props. The
 * tenant name therefore comes from the shared 'tenant' prop; here we add the
 * subscription/plan summary the dashboard needs.
 */
class TenantDashboardController extends Controller
{
    public function index(): Response
    {
        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        $subscription = $tenant->activeSubscription;

        // Trial days remaining: only meaningful while trialing and not lapsed.
        $trialDaysRemaining = null;
        if ($subscription?->status === \App\Modules\SaasCore\Models\Subscription::STATUS_TRIALING
            && $subscription->trial_ends_at !== null
            && $subscription->trial_ends_at->isFuture()
        ) {
            $trialDaysRemaining = (int) ceil(now()->diffInDays($subscription->trial_ends_at, false));
        }

        return Inertia::render('Tenant/Dashboard', [
            'planName' => $subscription?->plan?->name,
            'subscriptionStatus' => $subscription?->status,
            'trialDaysRemaining' => $trialDaysRemaining,
        ]);
    }
}
