<?php

use App\Modules\Workshop\Http\Controllers\MechanicAuthController;
use App\Modules\Workshop\Http\Controllers\MechanicPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Mechanic Portal Routes
|--------------------------------------------------------------------------
|
| Registered in bootstrap/app.php under the prefix: mechanic/{tenant_slug}
|   →  dvaro.com.au/mechanic/{tenant_slug}/...
| Guard: mechanic (separate guard, login required — not public).
|
| Group middleware ['web', 'mechanic.tenant'] runs BEFORE any route-level
| middleware, so the tenant is bound (ResolveTenantForMechanic) before
| 'auth:mechanic' loads the tenant-scoped Mechanic — the order auth-scoping
| depends on. The PUBLIC scan landing (mechanic.scan) lives in web.php.
|
*/

// Authentication — tenant context is bound, but no mechanic is required yet.
Route::get('login', [MechanicAuthController::class, 'showLogin'])->name('login');
Route::post('login', [MechanicAuthController::class, 'login'])->name('login.store');

// Authenticated mechanic portal.
Route::middleware('auth:mechanic')->group(function () {
    Route::post('logout', [MechanicAuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [MechanicPortalController::class, 'dashboard'])->name('dashboard');

    // Vehicle service page reached from a QR scan. Distinct path from the public
    // scan landing (mechanic/.../scan/{token}) to avoid a route collision.
    Route::get('vehicle/{token}', [MechanicPortalController::class, 'scanResult'])
        ->name('vehicle');

    // Service logs. {log} binds through TenantScope (cross-tenant id => 404).
    Route::post('logs', [MechanicPortalController::class, 'createLog'])->name('logs.store');
    Route::put('logs/{log}/status', [MechanicPortalController::class, 'updateStatus'])
        ->name('logs.status');
    Route::post('logs/{log}/parts', [MechanicPortalController::class, 'addPart'])
        ->name('logs.parts');
});
