<?php

namespace App\Http\Middleware;

use App\Modules\SaasCore\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves + binds the current tenant for the CUSTOMER portal route group.
 *
 * Deliberately separate from TenantMiddleware (which shares the TENANT-guard
 * user into Inertia and would mix guards on customer pages) and a sibling of
 * ResolveTenantForMechanic — same pattern, different guard. CLAUDE.md requires
 * the customer guard to stay fully isolated, so this shares ONLY the
 * customer-guard auth payload (plus the slim tenant payload).
 *
 * Binding current_tenant is mandatory here: CustomerUser is tenant-scoped
 * (HasTenant), so the guard's credential + retrieveById lookups would throw
 * TenantNotResolved without a bound tenant. This runs ahead of 'auth:customer'
 * via the same priority-list pin as TenantMiddleware (bootstrap/app.php).
 */
class ResolveTenantForCustomer
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
        // resolution shifts it into the next argument (e.g. an Invoice route-
        // model parameter receives the slug string → TypeError). Controllers read
        // the slug from current_tenant, never the route param, so this is safe.
        $request->route()?->forgetParameter('tenant_slug');

        Inertia::share('tenant', fn () => [
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'status' => $tenant->status,
        ]);

        // Customer-guard user only — resolved lazily after the guard has run.
        Inertia::share('auth', fn () => [
            'customer' => ($user = Auth::guard('customer')->user()) ? [
                'id' => $user->id,
                'customer_id' => $user->customer_id,
                'email' => $user->email,
            ] : null,
        ]);

        // Per-user dark/light preference + the endpoint to persist it.
        Inertia::share('colorMode', fn () => ($u = Auth::guard('customer')->user()) ? [
            'value' => $u->color_mode,
            'url' => route('customer.preferences.color-mode', ['tenant_slug' => $tenant->slug]),
        ] : null);

        return $next($request);
    }
}
