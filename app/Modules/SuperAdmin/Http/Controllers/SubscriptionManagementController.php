<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-wide subscription oversight for the super admin panel (read-only).
 *
 * Subscriptions are tenant-scoped (HasTenant); with no bound tenant here, every
 * query drops TenantScope explicitly. Authorize via billingAccess.
 */
class SubscriptionManagementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        $status = $request->query('status');
        $statuses = [
            Subscription::STATUS_ACTIVE,
            Subscription::STATUS_TRIALING,
            Subscription::STATUS_PAST_DUE,
            Subscription::STATUS_PAUSED,
            Subscription::STATUS_CANCELLED,
        ];
        if (! in_array($status, $statuses, true)) {
            $status = null;
        }

        $planId = $request->integer('plan_id') ?: null;

        $subscriptions = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with(['tenant:id,name,slug', 'plan:id,name'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($planId, fn ($q) => $q->where('plan_id', $planId))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Subscription $sub) => [
                'id' => $sub->id,
                'status' => $sub->status,
                'billing_cycle' => $sub->billing_cycle,
                'tenant_name' => $sub->tenant?->name,
                'tenant_slug' => $sub->tenant?->slug,
                'plan_name' => $sub->plan?->name,
                'current_period_end' => $sub->current_period_end,
                'created_at' => $sub->created_at,
            ]);

        return Inertia::render('SuperAdmin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'statuses' => $statuses,
            'activeStatus' => $status,
            'plans' => Plan::query()->orderBy('sort_order')->get(['id', 'name']),
            'activePlanId' => $planId,
        ]);
    }

    public function show(string $subscription): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('billingAccess');

        // Do NOT type-hint Subscription here: implicit route-model binding would
        // run TenantScope (no tenant is bound in the super admin panel) and throw.
        // Resolve the id manually without the scope instead.
        $subscription = Subscription::withoutGlobalScope(TenantScope::class)
            ->with(['tenant:id,name,slug', 'plan'])
            ->findOrFail($subscription);

        return Inertia::render('SuperAdmin/Subscriptions/Show', [
            'subscription' => [
                'id' => $subscription->id,
                'status' => $subscription->status,
                'billing_cycle' => $subscription->billing_cycle,
                'gateway' => $subscription->gateway,
                'gateway_subscription_id' => $subscription->gateway_subscription_id,
                'current_period_start' => $subscription->current_period_start,
                'current_period_end' => $subscription->current_period_end,
                'trial_ends_at' => $subscription->trial_ends_at,
                'cancelled_at' => $subscription->cancelled_at,
                'created_at' => $subscription->created_at,
                'tenant' => [
                    'name' => $subscription->tenant?->name,
                    'slug' => $subscription->tenant?->slug,
                ],
                'plan' => $subscription->plan ? [
                    'name' => $subscription->plan->name,
                    'price_monthly' => $subscription->plan->price_monthly,
                    'price_annual' => $subscription->plan->price_annual,
                ] : null,
            ],
            // Platform subscription billing (Stripe/PayPal) is a later session —
            // there is no platform-level payment ledger yet.
            'payments' => [],
        ]);
    }
}
