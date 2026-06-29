<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Http\Requests\CustomerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer-portal authentication (login / logout) for the 'customer' guard —
 * the FIFTH, fully isolated guard.
 *
 * Tenant context is bound by ResolveTenantForCustomer before any action here
 * runs, so app('current_tenant') is always available and the (tenant-scoped)
 * CustomerUser lookups resolve correctly. The explicit tenant_id in attempt()
 * is defence in depth on top of the automatic TenantScope: the SAME email can
 * exist for different customers across tenants, so the lookup must be pinned to
 * this tenant — one tenant's credentials can never authenticate against another.
 */
class CustomerAuthController extends Controller
{
    /** Mirrors the other portals: long enough that human-paced attempts lock out. */
    private const LOCKOUT_SECONDS = 900; // 15 minutes

    public function showLogin(): Response
    {
        return Inertia::render('Customer/Portal/Login');
    }

    public function login(CustomerLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $tenant = app('current_tenant');

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            // Belt-and-suspenders on top of TenantScope — pin to this tenant.
            'tenant_id' => $tenant->id,
        ];

        if (! Auth::guard('customer')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey(), self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('common.auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard', ['tenant_slug' => $tenant->slug]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $tenant = app('current_tenant');

        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.login', ['tenant_slug' => $tenant->slug]);
    }
}
