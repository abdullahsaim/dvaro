<?php

use App\Modules\SaasCore\Http\Controllers\TenantAuthController;
use App\Modules\SaasCore\Http\Controllers\TenantDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Path-based multi-tenancy. Registered in bootstrap/app.php under the
| prefix: app/{tenant_slug}  →  dvaro.com.au/app/{tenant_slug}/...
| Guard: tenant. Resolved by TenantMiddleware (alias: 'tenant').
|
| Group middleware ['web', 'tenant'] runs BEFORE any route-level middleware,
| so the tenant is resolved and bound before 'auth:tenant' attempts to load
| the user — the order auth-scoping depends on.
|
*/

// Authentication — tenant context is bound, but no user is required yet.
Route::get('login', [TenantAuthController::class, 'showLogin'])->name('login');
Route::post('login', [TenantAuthController::class, 'login'])->name('login.store');

// Authenticated tenant area.
Route::middleware('auth:tenant')->group(function () {
    Route::post('logout', [TenantAuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [TenantDashboardController::class, 'index'])->name('dashboard');
});
