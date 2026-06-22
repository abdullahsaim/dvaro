<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Guest only" gate for the PRE-TENANT public routes (e.g. /register).
 *
 * Why not the stock 'guest' middleware?
 *   - 'guest' (default web guard) never sees the tenant session, so it can't
 *     keep an already-signed-in tenant user off /register.
 *   - 'guest:tenant' WOULD detect it — but resolving the tenant user calls the
 *     provider's retrieveById(), a TenantScope-guarded query, and these routes
 *     have no bound current_tenant. That throws TenantNotResolvedException.
 *
 * So we detect a live tenant session by the PRESENCE of the tenant guard's
 * session key only — a pure session read, no model resolution, no DB query —
 * and bounce to the public root. (We can't resolve the tenant slug here without
 * a scoped lookup, so root is the safe, correct landing.)
 */
class RedirectIfTenantAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $sessionKey = Auth::guard('tenant')->getName();

        if ($request->session()->has($sessionKey)) {
            return redirect('/');
        }

        return $next($request);
    }
}
