<?php

use App\Modules\CRM\Http\Controllers\IntakeFormController;
use App\Modules\SaasCore\Http\Controllers\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Public CRM lead intake form
|--------------------------------------------------------------------------
|
| PUBLIC: no auth, no TenantMiddleware. The prospective customer opens these
| themselves via a signed link the tenant shares with them. Access is gated by
| the URL signature (tamper → 403) plus the lead's own expiry (→ 410), and the
| controller resolves the tenant from {tenant_slug} since none is bound here.
| The submit endpoint is throttled per token (5/hour) to curb form spam.
|
*/
Route::get('intake/{tenant_slug}/{token}', [IntakeFormController::class, 'show'])
    ->name('crm.intake.show');
Route::post('intake/{tenant_slug}/{token}', [IntakeFormController::class, 'submit'])
    ->middleware('throttle:crm-intake')
    ->name('crm.intake.submit');

/*
|--------------------------------------------------------------------------
| Public tenant self-registration
|--------------------------------------------------------------------------
|
| PRE-TENANT: these live in the 'web' group with NO TenantMiddleware and are
| intentionally NOT under the app/{tenant_slug} prefix — there is no tenant yet
| at signup. 'guest.tenant' keeps an already-signed-in tenant user off the form
| via a session-key check (no scoped lookup). See RedirectIfTenantAuthenticated.
|
*/
Route::middleware('guest.tenant')->group(function () {
    Route::get('register', [TenantRegistrationController::class, 'showRegister'])
        ->name('register');
    Route::post('register', [TenantRegistrationController::class, 'register'])
        ->name('register.store');
});
