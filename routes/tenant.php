<?php

use App\Modules\Agreement\Http\Controllers\AgreementController;
use App\Modules\CRM\Http\Controllers\LeadController;
use App\Modules\Customer\Http\Controllers\CustomerController;
use App\Modules\Fleet\Http\Controllers\FleetController;
use App\Modules\Invoice\Http\Controllers\InvoiceController;
use App\Modules\Notification\Http\Controllers\NotificationSettingsController;
use App\Modules\SaasCore\Http\Controllers\TenantAuthController;
use App\Modules\SaasCore\Http\Controllers\TenantDashboardController;
use App\Modules\Workshop\Http\Controllers\WorkshopController;
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

    // QR code — generate/regenerate (deterministic token) + inline stream of the
    // stored SVG (admin-only display). Generation lives in GenerateVehicleQrAction.
    Route::post('fleet/{vehicle}/qr', [FleetController::class, 'generateQr'])
        ->name('fleet.qr.generate');
    Route::get('fleet/{vehicle}/qr', [FleetController::class, 'qr'])
        ->name('fleet.qr');

    // Customers — {customer} binds through TenantScope (cross-tenant id => 404).
    Route::resource('customers', CustomerController::class);

    // Blacklist transitions go through their own endpoints → Blacklist/
    // UnblacklistCustomerAction (the only sanctioned paths, which fire events).
    Route::post('customers/{customer}/blacklist', [CustomerController::class, 'blacklist'])
        ->name('customers.blacklist');
    Route::post('customers/{customer}/unblacklist', [CustomerController::class, 'unblacklist'])
        ->name('customers.unblacklist');

    // Invite a customer to the Customer Portal (creates a 7-day invitation +
    // fires CustomerPortalInvitationSent). Idempotent — see the controller.
    Route::post('customers/{customer}/invite-portal', [CustomerController::class, 'invitePortal'])
        ->name('customers.invite-portal');

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

    // Mid-cycle vehicle change lives on InvoiceController (it is fundamentally an
    // invoice/proration operation). Two-step: preview (no writes) then confirm.
    Route::post('agreements/{agreement}/change-vehicle', [InvoiceController::class, 'changeVehicle'])
        ->name('agreements.change-vehicle');

    // Invoices — read-only resource ({invoice} binds through TenantScope =>
    // cross-tenant id 404). Invoices are generated by the engine, never created
    // by hand here. Payments + manual overdue go through their own endpoints.
    Route::resource('invoices', InvoiceController::class)->only(['index', 'show']);

    Route::post('invoices/{invoice}/payment', [InvoiceController::class, 'recordPayment'])
        ->name('invoices.payment');
    Route::post('invoices/{invoice}/overdue', [InvoiceController::class, 'markOverdue'])
        ->name('invoices.overdue');
    Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'downloadPdf'])
        ->name('invoices.pdf');

    // Notification settings — tenant-wide provider selection + channel toggles.
    // Not a resource (single settings page); tenant_admin-gated in the controller.
    Route::get('notifications/settings', [NotificationSettingsController::class, 'edit'])
        ->name('notifications.settings');
    Route::put('notifications/settings', [NotificationSettingsController::class, 'update'])
        ->name('notifications.settings.update');

    // Workshop (admin oversight, read-only). Service logs are created/mutated only
    // from the mechanic portal. {log}/{vehicle} bind through TenantScope (404).
    Route::get('workshop', [WorkshopController::class, 'index'])->name('workshop.index');
    Route::get('workshop/vehicle/{vehicle}', [WorkshopController::class, 'vehicleHistory'])
        ->name('workshop.vehicle');
    Route::get('workshop/{log}', [WorkshopController::class, 'show'])->name('workshop.show');
});
