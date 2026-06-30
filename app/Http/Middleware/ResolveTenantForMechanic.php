<?php

namespace App\Http\Middleware;

use App\Modules\SaasCore\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves + binds the current tenant for the MECHANIC portal route group.
 *
 * Deliberately separate from TenantMiddleware: that one shares the TENANT-guard
 * user into Inertia, which would leak/mix guards on mechanic pages. CLAUDE.md
 * requires the mechanic guard to stay fully isolated, so this middleware shares
 * ONLY the mechanic-guard auth payload (and the slim tenant payload).
 *
 * Binding current_tenant is mandatory here: the Mechanic model is tenant-scoped
 * (HasTenant), so the guard's credential + retrieveById lookups would throw
 * TenantNotResolved without a bound tenant. This runs ahead of 'auth:mechanic'
 * via the same priority-list pin as TenantMiddleware (bootstrap/app.php).
 */
class ResolveTenantForMechanic
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant_slug');

        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null) {
            abort(404, 'Tenant not found.');
        }

        if (in_array($tenant->status, [Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED], true)) {
            abort(403, 'This tenant account is not active.');
        }

        app()->instance('current_tenant', $tenant);

        // Drop {tenant_slug} from the route parameters now that the tenant is
        // bound. It is a leading prefix param that no authenticated controller
        // method declares; left in place, Laravel's positional dependency
        // resolution shifts it into the next argument (e.g. a route-model
        // {log} parameter receives the slug string → TypeError). Controllers read
        // the slug from current_tenant, never the route param, so this is safe.
        // Mirrors TenantMiddleware / ResolveTenantForCustomer.
        $request->route()?->forgetParameter('tenant_slug');

        Inertia::share('tenant', fn () => [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ]);

        // Mechanic-guard user only — resolved lazily after the guard has run.
        Inertia::share('auth', fn () => [
            'mechanic' => ($mechanic = Auth::guard('mechanic')->user()) ? [
                'id' => $mechanic->id,
                'name' => $mechanic->name,
                'email' => $mechanic->email,
            ] : null,
        ]);

        return $next($request);
    }
}
