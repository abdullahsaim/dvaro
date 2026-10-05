<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Exceptions\CheckoutNotAllowedException;
use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Http\Requests\CheckoutRequest;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\PaypalCheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

/**
 * PayPal subscription entry/exit points for the tenant billing page — mirrors
 * StripeCheckoutController exactly. TENANT-ADMIN ONLY ('manageSubscription'
 * gate via the load-bearing forUser pattern).
 *
 * checkout() hands the browser to PayPal's approval page; success() and
 * cancel() are merely where PayPal sends the browser BACK. Neither activates
 * anything — the return URL is reachable without approving, so activation
 * happens exclusively in the BILLING.SUBSCRIPTION.ACTIVATED webhook.
 */
class PaypalCheckoutController extends Controller
{
    public function checkout(
        CheckoutRequest $request,
        Plan $plan,
        PaypalCheckoutService $service,
    ): SymfonyResponse {
        Gate::forUser(auth('tenant')->user())->authorize('manageSubscription');

        /** @var Tenant $tenant */
        $tenant = app('current_tenant');
        $slug = $tenant->slug;

        $successUrl = route('tenant.billing.paypal.success', ['tenant_slug' => $slug]);
        $cancelUrl = route('tenant.billing.paypal.cancel', ['tenant_slug' => $slug]);

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
            report($e); // PayPal API/network failure — never leak the raw error

            return back()->with('error', __('common.billing.checkout_failed'));
        }

        // External redirect: Inertia::location makes the browser do a full
        // visit to PayPal's domain (a plain redirect() would be XHR-followed).
        return Inertia::location($url);
    }

    /**
     * PayPal redirected back after approval. Show "processing" ONLY — the
     * subscription is activated by the webhook, and this URL can be opened by
     * anyone without actually approving.
     */
    public function success(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewBilling');

        return Inertia::render('Billing/CheckoutProcessing');
    }

    /**
     * PayPal redirected back after the user abandoned approval. Nothing was
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
