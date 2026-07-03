<?php

use App\Modules\CMS\Http\Controllers\DemoRequestController;
use App\Modules\CMS\Http\Controllers\PublicLandingController;
use App\Modules\CRM\Http\Controllers\IntakeFormController;
use App\Modules\Customer\Http\Controllers\CustomerPortalController;
use App\Modules\SaasCore\Http\Controllers\TenantRegistrationController;
use App\Modules\SaasCore\Http\Controllers\WorkspaceLookupController;
use App\Http\Controllers\StripeWebhookController;
use App\Modules\Workshop\Http\Controllers\QrScanController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public landing website + CMS-driven marketing pages
|--------------------------------------------------------------------------
|
| PUBLIC: no auth, no tenant middleware. Editable copy comes from the CMS
| (super admin controlled); the pricing section always reads live Plan data.
| The demo-request and contact forms are throttled per IP (3/hour) to curb
| spam, and a contact enquiry is persisted as a DemoRequest so it surfaces in
| the same super admin queue.
|
*/
Route::get('/', [PublicLandingController::class, 'index'])->name('landing');
Route::get('pricing', [PublicLandingController::class, 'pricing'])->name('pricing');

// Global workspace lookup — resolves a tenant by email then forwards to its
// path-based login. PUBLIC, pre-tenant; the POST is throttled per IP (10/hour)
// to curb email enumeration.
Route::get('find-workspace', [WorkspaceLookupController::class, 'show'])->name('find-workspace');
Route::post('find-workspace', [WorkspaceLookupController::class, 'find'])
    ->middleware('throttle:workspace-lookup')
    ->name('find-workspace.find');

Route::get('about', [PublicLandingController::class, 'about'])->name('about');
Route::get('contact', [PublicLandingController::class, 'contact'])->name('contact');

Route::post('contact', [PublicLandingController::class, 'submitContact'])
    ->middleware('throttle:public-forms')
    ->name('contact.submit');
Route::post('demo-request', [DemoRequestController::class, 'store'])
    ->middleware('throttle:public-forms')
    ->name('demo-request.store');

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

/*
|--------------------------------------------------------------------------
| Public vehicle QR scan
|--------------------------------------------------------------------------
|
| PUBLIC: the URL encoded in every vehicle's QR sticker. No auth and NO tenant
| middleware — the tenant is NOT bound on entry. QrScanController resolves the
| tenant from {tenant_slug}, binds it, then finds the vehicle scope-free with an
| explicit tenant_id (the {token} is an unguessable HMAC). It exposes no data:
| an authenticated mechanic is redirected to the vehicle service page, a guest
| to the mechanic login (with the token stashed to return afterwards).
|
*/
Route::get('mechanic/{tenant_slug}/scan/{token}', [QrScanController::class, 'scan'])
    ->name('mechanic.scan');

/*
|--------------------------------------------------------------------------
| Public Customer Portal invitation acceptance
|--------------------------------------------------------------------------
|
| PUBLIC: no auth and NO customer.tenant middleware — the tenant is NOT bound
| on entry. CustomerPortalController resolves the tenant from {tenant_slug} and
| looks the invitation up scope-free by token AND explicit tenant_id (a token
| from tenant A can never be redeemed on tenant B). Accepting creates the
| CustomerUser, marks the invite used, and logs the customer in. Live portal
| routes (login/dashboard/...) live in routes/customer.php.
|
*/
Route::get('portal/{tenant_slug}/invite/{token}', [CustomerPortalController::class, 'showAcceptInvitation'])
    ->name('customer.portal.invite');
Route::post('portal/{tenant_slug}/invite/{token}', [CustomerPortalController::class, 'acceptInvitation'])
    ->name('customer.portal.invite.accept');

/*
|--------------------------------------------------------------------------
| Stripe webhook
|--------------------------------------------------------------------------
|
| SERVER-TO-SERVER: no auth middleware and NO CSRF (excluded in
| bootstrap/app.php) — the verified Stripe webhook signature is the only
| authentication; an invalid signature aborts 400 before any processing.
| This is where subscriptions actually activate (checkout.session.completed),
| never on the success-URL redirect.
|
*/
Route::post('stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->name('stripe.webhook');
