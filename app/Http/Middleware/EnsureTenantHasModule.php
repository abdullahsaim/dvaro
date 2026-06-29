<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hard-blocks access to a route group unless the bound tenant's active plan
 * includes the given module (CLAUDE.md: plan limits are HARD BLOCKS, never soft
 * warnings). Reusable across modules:
 *
 *     Route::middleware('tenant.module:ai')->group(...);
 *
 * Must run AFTER the tenant is bound (the tenant route group's 'tenant'
 * middleware resolves it) and is applied alongside 'auth:tenant'. A tenant whose
 * plan excludes the module gets a 403 — the AI nav/screens are simply not
 * reachable for them.
 */
class EnsureTenantHasModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $tenant = app()->bound('current_tenant') ? app('current_tenant') : null;

        abort_if($tenant === null || ! $tenant->hasModule($module), 403, __('ai.module_not_in_plan'));

        return $next($request);
    }
}
