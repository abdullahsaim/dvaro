<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\DTOs\TenantOnboardingDTO;
use App\Modules\SaasCore\Http\Requests\TenantRegistrationRequest;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantOnboardingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Public tenant self-registration (the pre-tenant signup flow).
 *
 * Served from the web route group with NO TenantMiddleware: there is no bound
 * current_tenant when these actions run. The register() action binds the newly
 * created tenant itself so the auto-login lookup is correctly scoped — see the
 * numbered sequence below.
 */
class TenantRegistrationController extends Controller
{
    public function showRegister(): Response
    {
        return Inertia::render('Tenant/Register', [
            // Same shape as the public pricing page's plan cards — the signup
            // wizard's plan step reuses that visual language. Free/cheapest
            // plan first (sort_order), so the default selection is the least
            // committal option.
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderByDesc('is_free')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Plan $plan) => [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'description' => $plan->description,
                    'price_monthly' => $plan->price_monthly,
                    'price_annual' => $plan->price_annual,
                    'is_free' => $plan->is_free,
                    'trial_days' => $plan->trial_days,
                    'limits' => $plan->limits ?? [],
                ])
                ->values(),
        ]);
    }

    public function register(
        TenantRegistrationRequest $request,
        TenantOnboardingService $service,
    ): RedirectResponse {
        $request->ensureIsNotRateLimited();
        $request->hitRateLimiter();

        try {
            // 1. Onboard the tenant (Tenant + Subscription + admin TenantUser),
            //    all in one transaction inside the service. Status is PENDING
            //    instead of TRIAL when the platform requires manual approval.
            $tenant = $service->execute(TenantOnboardingDTO::fromRequest($request));

            if ($tenant->status === Tenant::STATUS_PENDING) {
                // Awaiting a super admin's approval — nothing to log into yet.
                // No tenant bind needed: this page carries no tenant-scoped data.
                return redirect()->route('register.pending');
            }

            // 2. Bind the new tenant FIRST. TenantUser uses HasTenant, so every
            //    SELECT runs through TenantScope, which THROWS when no tenant is
            //    bound. This bind must happen before the query on step 3 — any
            //    other order throws TenantNotResolvedException right after a
            //    successful registration. (Order here is load-bearing.)
            app()->instance('current_tenant', $tenant);

            // 3. Query the admin user — now correctly scoped to the new tenant.
            $user = TenantUser::where('email', $request->string('email')->toString())
                ->firstOrFail();

            // 4. Log the admin in on the tenant guard.
            Auth::guard('tenant')->login($user);
        } catch (Throwable $e) {
            // Never leak the underlying failure to the user; log it and show a
            // friendly message. The transaction has already rolled back.
            report($e);

            return back()->withErrors([
                'email' => __('common.register.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('tenant.dashboard', ['tenant_slug' => $tenant->slug]);
    }

    /**
     * Static landing page shown after registering onto a platform that
     * requires manual tenant approval. Carries no tenant-specific data — the
     * admin cannot log in yet, so there is nothing scoped to show them.
     */
    public function pending(): Response
    {
        return Inertia::render('Tenant/RegistrationPending');
    }
}
