<?php

use App\Http\Controllers\UserPreferenceController;
use App\Modules\SuperAdmin\Http\Controllers\CmsContentController;
use App\Modules\SuperAdmin\Http\Controllers\DemoRequestController;
use App\Modules\SuperAdmin\Http\Controllers\PlanManagementController;
use App\Modules\SuperAdmin\Http\Controllers\SubscriptionManagementController;
use App\Modules\SuperAdmin\Http\Controllers\SuperAdminAuthController;
use App\Modules\SuperAdmin\Http\Controllers\SuperAdminDashboardController;
use App\Modules\SuperAdmin\Http\Controllers\SystemSettingsController;
use App\Modules\SuperAdmin\Http\Controllers\TenantManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Super Admin Routes
|--------------------------------------------------------------------------
|
| Registered in bootstrap/app.php under the prefix: superadmin
|   →  dvaro.com.au/superadmin/...
| Guard: superadmin (separate from all tenant/customer/mechanic guards).
|
| The authenticated area is gated by the 'superadmin.auth' middleware
| (SuperAdminMiddleware: superadmin guard + is_active + shared auth prop).
|
*/

// Authentication — no super admin required yet.
Route::get('login', [SuperAdminAuthController::class, 'showLogin'])->name('login');
Route::post('login', [SuperAdminAuthController::class, 'login'])->name('login.store');

// Authenticated super admin panel.
Route::middleware('superadmin.auth')->group(function () {
    Route::post('logout', [SuperAdminAuthController::class, 'logout'])->name('logout');

    // Per-user UI preference (dark/light) — shared controller, one per guard.
    Route::put('preferences/color-mode', [UserPreferenceController::class, 'updateColorMode'])
        ->name('preferences.color-mode');

    Route::get('dashboard', [SuperAdminDashboardController::class, 'index'])->name('dashboard');

    // Tenants — {tenant} binds by slug (Tenant::getRouteKeyName). Tenant is NOT
    // tenant-scoped, so implicit binding is safe here.
    Route::get('tenants', [TenantManagementController::class, 'index'])->name('tenants.index');
    Route::get('tenants/{tenant}', [TenantManagementController::class, 'show'])->name('tenants.show');
    Route::post('tenants/{tenant}/suspend', [TenantManagementController::class, 'suspend'])
        ->name('tenants.suspend');
    Route::post('tenants/{tenant}/activate', [TenantManagementController::class, 'activate'])
        ->name('tenants.activate');
    Route::post('tenants/{tenant}/impersonate', [TenantManagementController::class, 'impersonate'])
        ->name('tenants.impersonate');

    // Stop impersonating — clears the tenant guard + session marker (both).
    Route::post('stop-impersonating', [TenantManagementController::class, 'stopImpersonating'])
        ->name('stop-impersonating');

    // Plans — editable, but NO destroy (a plan with subscriptions must not be
    // deleted; toggle is_active instead). No show route (Index lists everything).
    Route::resource('plans', PlanManagementController::class)
        ->except(['show', 'destroy']);
    Route::post('plans/{plan}/toggle', [PlanManagementController::class, 'toggle'])
        ->name('plans.toggle');

    // Subscriptions — read-only oversight.
    Route::get('subscriptions', [SubscriptionManagementController::class, 'index'])
        ->name('subscriptions.index');
    Route::get('subscriptions/{subscription}', [SubscriptionManagementController::class, 'show'])
        ->name('subscriptions.show');

    // Platform settings.
    Route::get('settings', [SystemSettingsController::class, 'show'])->name('settings');
    Route::put('settings', [SystemSettingsController::class, 'update'])->name('settings.update');

    // Landing-page CMS. {key} is the content block's unique string key (validated
    // in the request). All actions are content-access gated.
    Route::get('cms', [CmsContentController::class, 'index'])->name('cms.index');
    Route::put('cms/{key}', [CmsContentController::class, 'update'])->name('cms.update');
    Route::post('cms/{key}/image', [CmsContentController::class, 'updateImage'])->name('cms.image');

    // Demo / contact requests queue. {demoRequest} binds implicitly (platform-
    // wide model, no TenantScope).
    Route::get('demo-requests', [DemoRequestController::class, 'index'])->name('demo-requests.index');
    Route::post('demo-requests/{demoRequest}/contacted', [DemoRequestController::class, 'markContacted'])
        ->name('demo-requests.contacted');
});
