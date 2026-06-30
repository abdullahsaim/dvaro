<?php

use App\Modules\Customer\Http\Controllers\CustomerAuthController;
use App\Modules\Customer\Http\Controllers\CustomerPasswordResetController;
use App\Modules\Customer\Http\Controllers\CustomerPortalController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Customer Portal Routes
|--------------------------------------------------------------------------
|
| Registered in bootstrap/app.php under the prefix: portal/{tenant_slug}
|   →  dvaro.com.au/portal/{tenant_slug}/...
| Guard: customer (the FIFTH guard, fully isolated).
|
| Group middleware ['web', 'customer.tenant'] runs BEFORE any route-level
| middleware, so the tenant is bound (ResolveTenantForCustomer) before
| 'auth:customer' loads the tenant-scoped CustomerUser — the order the
| auth-scoping depends on. The PUBLIC invitation-acceptance routes live in
| web.php (no bound tenant on entry; the controller resolves it from the slug).
|
*/

// Authentication — tenant context is bound, but no customer is required yet.
Route::get('login', [CustomerAuthController::class, 'showLogin'])->name('login');
Route::post('login', [CustomerAuthController::class, 'login'])->name('login.store');

// Password reset (guest flow; tenant bound by ResolveTenantForCustomer). The
// reset link path carries {tenant_slug}. sendResetLink → 3/email/hour.
Route::get('forgot-password', [CustomerPasswordResetController::class, 'showRequestForm'])
    ->name('password.request');
Route::post('forgot-password', [CustomerPasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:password-reset')->name('password.email');
Route::get('reset-password/{token}', [CustomerPasswordResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::post('reset-password', [CustomerPasswordResetController::class, 'reset'])
    ->name('password.update');

// Authenticated customer portal. {invoice}/{agreement} bind through TenantScope
// (cross-tenant id => 404); per-customer scoping is enforced in the controller
// + CustomerPortalPolicy (own customer_id only).
Route::middleware('auth:customer')->group(function () {
    Route::post('logout', [CustomerAuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [CustomerPortalController::class, 'dashboard'])->name('dashboard');

    Route::get('invoices', [CustomerPortalController::class, 'invoices'])->name('invoices.index');
    Route::get('invoices/{invoice}', [CustomerPortalController::class, 'showInvoice'])->name('invoices.show');
    Route::get('invoices/{invoice}/pdf', [CustomerPortalController::class, 'downloadInvoicePdf'])->name('invoices.pdf');
    Route::post('invoices/{invoice}/pay', [CustomerPortalController::class, 'makePayment'])->name('invoices.pay');

    Route::get('agreements', [CustomerPortalController::class, 'agreements'])->name('agreements.index');
    Route::get('agreements/{agreement}', [CustomerPortalController::class, 'showAgreement'])->name('agreements.show');
    Route::get('agreements/{agreement}/pdf', [CustomerPortalController::class, 'downloadAgreementPdf'])->name('agreements.pdf');
});
