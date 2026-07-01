<?php

namespace App\Http\Middleware;

use App\Modules\SaasCore\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the current tenant from the {tenant_slug} path segment and binds
 * it to the container as 'current_tenant' for the lifetime of the request.
 *
 * Attached to the tenant route group via the 'tenant' alias (bootstrap/app.php).
 */
class TenantMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->route('tenant_slug');

        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null) {
            abort(404, 'Tenant not found.');
        }

        // Hard block inactive tenants. Active + trial may proceed.
        if (in_array($tenant->status, [Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED], true)) {
            abort(403, 'This tenant account is not active.');
        }

        // Bind for TenantScope / HasTenant and the rest of the request.
        app()->instance('current_tenant', $tenant);

        // Drop {tenant_slug} from the route parameters now that the tenant is
        // bound. It is a leading prefix param that no authenticated controller
        // method declares; left in place, Laravel's positional dependency
        // resolution shifts it into the next argument (e.g. a route-model
        // {conversation}/{invoice} parameter receives the slug string →
        // TypeError). Controllers read the slug from current_tenant, never the
        // route param, so this is safe. Mirrors ResolveTenantForCustomer/Mechanic.
        $request->route()?->forgetParameter('tenant_slug');

        // Expose a slim, safe tenant payload to every Inertia page.
        Inertia::share('tenant', fn () => [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ]);

        // Resolved lazily at render time (after the guard has run): the current
        // tenant user, or null when unauthenticated.
        Inertia::share('auth', fn () => [
            'user' => ($user = Auth::guard('tenant')->user()) ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ] : null,
        ]);

        // Per-user dark/light preference + the endpoint to persist it. Read by
        // the useColorMode composable; null when unauthenticated (localStorage
        // still drives the UI on public/guest pages).
        Inertia::share('colorMode', fn () => ($u = Auth::guard('tenant')->user()) ? [
            'value' => $u->color_mode,
            'url' => route('tenant.preferences.color-mode', ['tenant_slug' => $tenant->slug]),
        ] : null);

        return $next($request);
    }
}
