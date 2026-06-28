<?php

use App\Http\Middleware\CheckImpersonation;
use App\Http\Middleware\RedirectIfTenantAuthenticated;
use App\Http\Middleware\ResolveTenantForMechanic;
use App\Http\Middleware\SuperAdminMiddleware;
use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Tenant app — path-based multi-tenancy: /app/{tenant_slug}/...
            // 'tenant' middleware resolves + binds the tenant from the slug;
            // 'check.impersonation' shares the super admin impersonation banner
            // state (session-only) into the tenant UI.
            Route::middleware(['web', 'tenant', 'check.impersonation'])
                ->prefix('app/{tenant_slug}')
                ->name('tenant.')
                ->group(base_path('routes/tenant.php'));

            // Super admin panel — /superadmin/...  (superadmin guard; separate
            // from every tenant guard). Per-route 'superadmin.auth' gates the
            // authenticated area inside routes/superadmin.php.
            Route::middleware('web')
                ->prefix('superadmin')
                ->name('superadmin.')
                ->group(base_path('routes/superadmin.php'));

            // Customer portal — /portal/{tenant_slug}/...
            Route::middleware('web')
                ->prefix('portal/{tenant_slug}')
                ->name('customer.')
                ->group(base_path('routes/customer.php'));

            // Mechanic portal — /mechanic/{tenant_slug}/...
            // 'mechanic.tenant' binds the tenant from the slug (the Mechanic
            // model is tenant-scoped) WITHOUT sharing the tenant-guard user —
            // the mechanic guard stays isolated. See ResolveTenantForMechanic.
            Route::middleware(['web', 'mechanic.tenant'])
                ->prefix('mechanic/{tenant_slug}')
                ->name('mechanic.')
                ->group(base_path('routes/mechanic.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Tenant resolution middleware. Attach to tenant-scoped route groups
        // once the resolver is implemented:  ->middleware('tenant')
        $middleware->alias([
            'tenant' => TenantMiddleware::class,
            // Mechanic portal tenant resolver (mechanic-guard isolated).
            'mechanic.tenant' => ResolveTenantForMechanic::class,
            // "Guest only" for pre-tenant public routes (e.g. /register).
            // Session-key check only — never resolves the tenant user, which
            // would trip TenantScope on these unbound routes. See the class.
            'guest.tenant' => RedirectIfTenantAuthenticated::class,
            // Super admin panel gate (superadmin guard + active check).
            'superadmin.auth' => SuperAdminMiddleware::class,
            // Shares super admin impersonation banner state into the tenant UI.
            'check.impersonation' => CheckImpersonation::class,
        ]);

        // Register Inertia's server-side middleware on the web group. Required so
        // session-flashed validation errors are shared into Inertia page props.
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);

        // CRITICAL ordering: Laravel's middleware-priority sort hoists the
        // framework Authenticate middleware ('auth:tenant') ahead of unsorted
        // custom middleware. Without this, auth would run BEFORE TenantMiddleware
        // resolves the tenant — breaking tenant-scoped auth lookups and the guest
        // redirect below. Pin TenantMiddleware into the priority list immediately
        // before the auth middleware so tenant context is always bound first.
        // NB: the default priority list references the AuthenticatesRequests
        // CONTRACT (the router matches Authenticate to it via instanceof), so we
        // anchor to the interface, not the concrete Authenticate class.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: TenantMiddleware::class,
        );

        // Same pin for the mechanic portal: ResolveTenantForMechanic must bind
        // the tenant before 'auth:mechanic' loads the (tenant-scoped) Mechanic.
        $middleware->prependToPriorityList(
            before: \Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests::class,
            prepend: ResolveTenantForMechanic::class,
        );

        // Where 'auth:tenant' sends unauthenticated guests. TenantMiddleware
        // runs earlier in the pipeline and binds 'current_tenant', so we detect
        // tenant context from that single source of truth rather than re-parsing
        // the path. No tenant bound (other route groups) → fall back to root.
        $middleware->redirectGuestsTo(function (Request $request) {
            if (app()->bound('current_tenant')) {
                $slug = app('current_tenant')->slug;

                // Mechanic portal routes have their own login — never bounce a
                // guest mechanic to the tenant login.
                if ($request->is('mechanic/*')) {
                    return route('mechanic.login', ['tenant_slug' => $slug]);
                }

                return route('tenant.login', ['tenant_slug' => $slug]);
            }

            return '/';
        });
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // TenantNotResolvedException is intentionally NOT added to dontReport():
        // it signals a tenant-context bug and must surface fully in development.
    })->create();
