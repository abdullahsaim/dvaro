<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Exceptions\CheckoutNotAllowedException;
use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Http\Requests\CheckoutRequest;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\StripeCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * Stripe Checkout entry/exit points for the tenant billing page — TENANT-ADMIN
 * ONLY ('manageSubscription' gate via the load-bearing forUser pattern).
 *
 * checkout() hands the browser to Stripe's hosted page; success() and cancel()
 * are merely where Stripe sends the browser BACK. Neither activates anything —
 * the success URL is reachable without paying, so activation happens
 * exclusively in the checkout.session.completed webhook.
 */
class StripeCheckoutController extends Controller
{
    public function checkout(
        CheckoutRequest $request,
        Plan $plan,
        StripeCheckoutService $service,
    ): SymfonyResponse {
        Gate::forUser(auth('tenant')->user())->authorize('manageSubscription');

        /** @var Tenant $tenant */
        $tenant = app('current_tenant');
        $slug = $tenant->slug;

        // Stripe substitutes {CHECKOUT_SESSION_ID} itself — appended raw
        // because route() would URL-encode the braces.
        $successUrl = route('tenant.billing.checkout.success', ['tenant_slug' => $slug])
            .'?session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = route('tenant.billing.checkout.cancel', ['tenant_slug' => $slug]);

        try {
            $url = $service->checkoutUrl(
                $tenant,
                $plan,
                $request->validated('billing_cycle'),
                $successUrl,
                $cancelUrl,
            );
        } catch (CheckoutNotAllowedException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e); // Stripe API/network failure — never leak the raw error
            return back()->with('error', __('common.billing.checkout_failed'));
        }

        // External redirect: Inertia::location makes the browser do a full
        // visit to Stripe's domain (a plain redirect() would be XHR-followed).
        return Inertia::location($url);
    }

    /**
     * Stripe redirected back after payment. Show "processing" ONLY — the
     * subscription is activated by the webhook, and this URL can be opened by
     * anyone without paying.
     */
    public function success(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewBilling');

        return Inertia::render('Billing/CheckoutProcessing');
    }

    /**
     * Stripe redirected back after the user abandoned checkout. Nothing was
     * charged; nothing to clean up.
     */
    public function cancel(): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewBilling');

        /** @var Tenant $tenant */
        $tenant = app('current_tenant');

        return redirect()
            ->route('tenant.billing.index', ['tenant_slug' => $tenant->slug])
            ->with('error', __('common.billing.checkout_cancelled'));
    }
}
