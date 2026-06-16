<?php

use App\Http\Middleware\TenantMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Tenant app — path-based multi-tenancy: /app/{tenant_slug}/...
            Route::middleware('web')
                ->prefix('app/{tenant_slug}')
                ->name('tenant.')
                ->group(base_path('routes/tenant.php'));

            // Super admin panel — /superadmin/...
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
            Route::middleware('web')
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
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
