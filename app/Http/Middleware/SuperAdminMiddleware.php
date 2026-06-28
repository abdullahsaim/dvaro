<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the super admin panel (alias 'superadmin.auth').
 *
 * Verifies the request is authenticated on the SUPERADMIN guard and that the
 * account is active, then shares a slim super-admin auth payload to every
 * Inertia page in the panel. The superadmin guard is fully isolated — this
 * never touches the tenant / customer / mechanic guards.
 *
 * Super admins are platform-wide (no TenantScope), so there is no tenant to
 * resolve here, unlike the tenant/mechanic middleware.
 */
class SuperAdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $admin = Auth::guard('superadmin')->user();

        if ($admin === null) {
            return redirect()->route('superadmin.login');
        }

        // A deactivated account is logged straight back out.
        if (! $admin->is_active) {
            Auth::guard('superadmin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('superadmin.login');
        }

        // Bind for any platform-level logic that wants the current super admin.
        app()->instance('current_super_admin', $admin);

        // Slim, safe super-admin payload for the panel UI.
        Inertia::share('auth', fn () => [
            'superAdmin' => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'role' => $admin->role,
            ],
        ]);

        return $next($request);
    }
}
