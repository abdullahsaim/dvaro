<?php

use App\Modules\SaasCore\Http\Controllers\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

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
