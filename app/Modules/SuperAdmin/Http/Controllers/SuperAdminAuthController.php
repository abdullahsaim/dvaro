<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Http\Requests\SuperAdminLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin panel authentication (login / logout) for the 'superadmin' guard.
 *
 * The superadmin guard is platform-wide and fully separate from the tenant,
 * customer and mechanic guards. No tenant scoping applies — credential lookups
 * run against all super admins.
 */
class SuperAdminAuthController extends Controller
{
    /**
     * Failed-login lockout window — same pattern as the tenant login. Long
     * enough that human-paced attempts accumulate toward the 5-attempt limit.
     */
    private const LOCKOUT_SECONDS = 900; // 15 minutes

    public function showLogin(): Response
    {
        return Inertia::render('SuperAdmin/Login');
    }

    public function login(SuperAdminLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $credentials = [
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            // Only active accounts may authenticate.
            'is_active' => true,
        ];

        if (! Auth::guard('superadmin')->attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($request->throttleKey(), self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('common.auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        // Stamp last login.
        $admin = Auth::guard('superadmin')->user();
        $admin->forceFill(['last_login_at' => now()])->save();

        return redirect()->route('superadmin.dashboard');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('superadmin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('superadmin.login');
    }
}
