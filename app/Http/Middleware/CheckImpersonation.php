<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Surfaces super admin impersonation state to the tenant app (alias
 * 'check.impersonation'), applied to the tenant route group.
 *
 * Impersonation is SESSION-ONLY and never modifies the DB: when a super admin
 * impersonates a tenant, TenantManagementController logs into the tenant guard
 * as that tenant's first admin user and stamps 'impersonator_superadmin_id' in
 * the session. This middleware detects that marker and shares an 'impersonating'
 * Inertia prop so the tenant UI can render a banner with a "stop" control.
 *
 * The banner reads ONLY from this shared prop (no extra API call / localStorage)
 * — consistent with how the 'tenant' and 'auth' props are shared.
 */
class CheckImpersonation
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('impersonating', function () use ($request) {
            if (! $request->session()->has('impersonator_superadmin_id')) {
                return null;
            }

            return [
                'active' => true,
            ];
        });

        return $next($request);
    }
}
