<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-portal authentication (login / logout) for the 'tenant' guard.
 *
 * Tenant context is bound by TenantMiddleware before any action here runs, so
 * app('current_tenant') is always available. Credential and id lookups go
 * through TenantScope, so a login can only ever match a user of the bound
 * tenant; the explicit tenant_id in the attempt() credentials is defence in
 * depth on top of that scoping.
 */
class TenantAuthController extends Controller
{
    /**
     * Failed-login lockout window. Counts toward the 5-attempt limit in
     * LoginRequest and is also how long the lockout lasts. Must be long enough
     * that human-paced attempts accumulate — the framework default of 60s
     * silently resets between slow attempts and never locks out.
     */
    private const LOCKOUT_SECONDS = 900; // 15 minutes

    public function showLogin(): Response
    {
        return Inertia::render('Tenant/Login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $tenant = app('current_tenant');

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            // Belt-and-suspenders: constrain the lookup to this tenant on top
            // of the automatic TenantScope global scope.
            'tenant_id' => $tenant->id,
        ];

        if (! Auth::guard('tenant')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey(), self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('common.auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        return redirect()->route('tenant.dashboard', ['tenant_slug' => $tenant->slug]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $tenant = app('current_tenant');

        Auth::guard('tenant')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('tenant.login', ['tenant_slug' => $tenant->slug]);
    }
}
