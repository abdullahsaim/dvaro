<?php

use App\Modules\Agreement\Http\Controllers\AgreementController;
use App\Modules\CRM\Http\Controllers\LeadController;
use App\Modules\Customer\Http\Controllers\CustomerController;
use App\Modules\Fleet\Http\Controllers\FleetController;
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

    // Fleet — resource binds {vehicle} (instead of the default {fleet}) so model
    // binding resolves a Vehicle through TenantScope (cross-tenant id => 404).
    Route::resource('fleet', FleetController::class)
        ->parameter('fleet', 'vehicle');

    // Status transitions go through their own endpoint → ChangeVehicleStatusAction.
    Route::post('fleet/{vehicle}/status', [FleetController::class, 'changeStatus'])
        ->name('fleet.status');

    // Customers — {customer} binds through TenantScope (cross-tenant id => 404).
    Route::resource('customers', CustomerController::class);

    // Blacklist transitions go through their own endpoints → Blacklist/
    // UnblacklistCustomerAction (the only sanctioned paths, which fire events).
    Route::post('customers/{customer}/blacklist', [CustomerController::class, 'blacklist'])
        ->name('customers.blacklist');
    Route::post('customers/{customer}/unblacklist', [CustomerController::class, 'unblacklist'])
        ->name('customers.unblacklist');

    // CRM leads — {lead} binds through TenantScope (cross-tenant id => 404).
    // No edit/update: a lead is captured, shared, then converted/expired — it is
    // not an editable record (the customer edits via the public intake form).
    Route::resource('leads', LeadController::class)->except(['edit', 'update']);

    // Conversion + manual expiry go through their own endpoints → Convert/
    // ExpireLeadAction (the only sanctioned paths, which fire events).
    Route::post('leads/{lead}/convert', [LeadController::class, 'convert'])
        ->name('leads.convert');
    Route::post('leads/{lead}/expire', [LeadController::class, 'expire'])
        ->name('leads.expire');

    // Agreements — {agreement} binds through TenantScope (cross-tenant id => 404).
    // IMMUTABLE: no edit/update/destroy — agreements are never edited or deleted.
    // State changes only via sign (status transition) and version (a NEW row).
    Route::resource('agreements', AgreementController::class)
        ->except(['edit', 'update', 'destroy']);

    Route::post('agreements/{agreement}/sign', [AgreementController::class, 'sign'])
        ->name('agreements.sign');
    Route::post('agreements/{agreement}/version', [AgreementController::class, 'createVersion'])
        ->name('agreements.version');

    // Stream the queued-and-stored PDF (read-only). pdf_path null => 404.
    Route::get('agreements/{agreement}/pdf', [AgreementController::class, 'downloadPdf'])
        ->name('agreements.pdf');
});
