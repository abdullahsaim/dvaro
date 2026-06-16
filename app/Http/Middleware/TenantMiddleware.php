<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active tenant from the {tenant_slug} path segment and binds
 * it into the container/context for the duration of the request.
 *
 * SKELETON ONLY — passthrough. No resolution logic yet.
 *
 * Path-based multi-tenancy: dvaro.com.au/app/{tenant_slug}/...
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // TODO: resolve tenant from {tenant_slug}, abort 404 if not found,
        // bind current tenant into context for TenantScope.

        return $next($request);
    }
}
