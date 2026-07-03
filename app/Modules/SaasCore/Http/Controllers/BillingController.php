<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Exceptions\CheckoutNotAllowedException;
use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Actions\CancelStripeSubscriptionAction;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\UsageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Tenant billing portal — TENANT-ADMIN ONLY.
 *
 * Runs behind ['web', 'tenant', 'auth:tenant'], so current_tenant is bound and
 * every count in UsageService is tenant-scoped. Authorization is the model-less
 * 'viewBilling' / 'manageSubscription' gates (BillingPolicy) via the
 * load-bearing forUser pattern — NOT $this->authorize(), which would resolve
 * the empty web guard.
 *
 * Mutations from here: submitting an UpgradeRequest (UpgradeRequestController),
 * starting a Stripe checkout (StripeCheckoutController) and requesting a
 * Stripe cancellation (cancelSubscription below — cancel-at-period-end; the
 * definitive local cancellation arrives via webhook).
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
                // Self-service Stripe checkout per cycle — only when the plan
                // is paid AND synced to Stripe (stripe:sync-plans).
                'can_checkout_monthly' => ! $p->is_free && $p->stripe_monthly_price_id !== null,
                'can_checkout_annual' => ! $p->is_free && $p->stripe_annual_price_id !== null,
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
                'gateway' => $subscription->gateway,
                'stripe_status' => $subscription->stripe_status,
                // Cancellable = billed through Stripe and not already winding down.
                'can_cancel' => $subscription->gateway === Subscription::GATEWAY_STRIPE
                    && filled($subscription->gateway_subscription_id)
                    && $subscription->stripe_status !== Subscription::STRIPE_STATUS_CANCELING,
            ],
            'trialDaysRemaining' => $trialDaysRemaining,
            'usage' => $usage->getUsage($tenant),
            'disabledModules' => $disabledModules,
            'availablePlans' => $availablePlans,
            'history' => $history,
        ]);
    }

    /**
     * Ask Stripe to cancel at period end (CancelStripeSubscriptionAction sets
     * stripe_status='canceling'; access continues until the period lapses and
     * the customer.subscription.deleted webhook cancels locally).
     */
    public function cancelSubscription(CancelStripeSubscriptionAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('manageSubscription');

        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        try {
            $action->execute($tenant);
        } catch (CheckoutNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e); // Stripe API/network failure — never leak the raw error
            return back()->with('error', __('common.billing.cancel_failed'));
        }

        return back()->with('success', __('common.billing.cancel_requested'));
    }
}
