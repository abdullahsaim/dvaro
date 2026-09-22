<?php

use App\Http\Controllers\UserPreferenceController;
use App\Modules\Agreement\Http\Controllers\AgreementController;
use App\Modules\Agreement\Http\Controllers\AgreementTemplateController;
use App\Modules\AI\Http\Controllers\AiController;
use App\Modules\CRM\Http\Controllers\LeadController;
use App\Modules\CRM\Http\Controllers\LeadFormController;
use App\Modules\Customer\Http\Controllers\CustomerController;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Http\Controllers\ExpenseCategoryController;
use App\Modules\Finance\Http\Controllers\ExpenseController;
use App\Modules\Fleet\Http\Controllers\FleetController;
use App\Modules\Invoice\Http\Controllers\InvoiceController;
use App\Modules\Invoice\Http\Controllers\InvoiceTemplateController;
use App\Modules\Notification\Http\Controllers\NotificationSettingsController;
use App\Modules\Reporting\Http\Controllers\ReportingController;
use App\Modules\SaasCore\Http\Controllers\BillingController;
use App\Modules\SaasCore\Http\Controllers\CompanyProfileController;
use App\Modules\SaasCore\Http\Controllers\FinanceSettingsController;
use App\Modules\SaasCore\Http\Controllers\IntegrationSettingsController;
use App\Modules\SaasCore\Http\Controllers\RegionalSettingsController;
use App\Modules\SaasCore\Http\Controllers\SettingsController;
use App\Modules\SaasCore\Http\Controllers\StaffController;
use App\Modules\SaasCore\Http\Controllers\StaffInvitationController;
use App\Modules\SaasCore\Http\Controllers\StripeCheckoutController;
use App\Modules\SaasCore\Http\Controllers\TenantAuthController;
use App\Modules\SaasCore\Http\Controllers\TenantDashboardController;
use App\Modules\SaasCore\Http\Controllers\TenantPasswordResetController;
use App\Modules\SaasCore\Http\Controllers\TenantProfileController;
use App\Modules\SaasCore\Http\Controllers\UpgradeRequestController;
use App\Modules\Workshop\Http\Controllers\MechanicController;
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

// Password reset (guest flow; tenant bound by TenantMiddleware so the broker's
// user lookup is tenant-scoped). The reset link path carries {tenant_slug}.
// sendResetLink is throttled to 3/email/hour (password-reset limiter).
Route::get('forgot-password', [TenantPasswordResetController::class, 'showRequestForm'])
    ->name('password.request');
Route::post('forgot-password', [TenantPasswordResetController::class, 'sendResetLink'])
    ->middleware('throttle:password-reset')->name('password.email');
Route::get('reset-password/{token}', [TenantPasswordResetController::class, 'showResetForm'])
    ->name('password.reset');
Route::post('reset-password', [TenantPasswordResetController::class, 'reset'])
    ->name('password.update');

// Staff invitation acceptance (PUBLIC — the invitee has no account yet; the
// unguessable token is the only credential). Tenant is bound by TenantMiddleware.
Route::get('invitation/{token}', [StaffInvitationController::class, 'show'])
    ->name('staff.invite.show');
Route::post('invitation/{token}', [StaffInvitationController::class, 'accept'])
    ->middleware('throttle:6,1')->name('staff.invite.accept');

// Authenticated tenant area.
Route::middleware('auth:tenant')->group(function () {
    Route::post('logout', [TenantAuthController::class, 'logout'])->name('logout');

    // Per-user UI preference (dark/light) — shared controller, one per guard.
    Route::put('preferences/color-mode', [UserPreferenceController::class, 'updateColorMode'])
        ->name('preferences.color-mode');

    // Own profile — name/email + password (self-service, own row only).
    Route::get('profile', [TenantProfileController::class, 'showProfile'])->name('profile');
    Route::put('profile', [TenantProfileController::class, 'updateProfile'])->name('profile.update');
    Route::put('profile/password', [TenantProfileController::class, 'updatePassword'])
        ->name('profile.password');
    // Personal settings — own row only (landing page, rows per page, opt-outs).
    Route::put('profile/preferences', [TenantProfileController::class, 'updatePreferences'])
        ->name('profile.preferences');

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
    // Staff odometer entry → RecordOdometerReadingAction (append-only history,
    // never backwards).
    Route::post('fleet/{vehicle}/odometer', [FleetController::class, 'recordOdometer'])
        ->name('fleet.odometer');

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

    // Customer identity documents (licence front/back, proof of address).
    // SENSITIVE: stored on the default (private) disk; download is auth-checked
    // and only ever redirects to a short-lived signed URL (FileUrlService).
    Route::post('customers/{customer}/documents/{type}', [CustomerController::class, 'uploadDocument'])
        ->whereIn('type', array_keys(Customer::DOCUMENT_TYPES))
        ->name('customers.documents.upload');
    Route::get('customers/{customer}/documents/{type}', [CustomerController::class, 'downloadDocument'])
        ->whereIn('type', array_keys(Customer::DOCUMENT_TYPES))
        ->name('customers.documents.download');

    // Finance → Expenses (Session 32). {expense}/{category} bind via
    // TenantScope (cross-tenant id → 404). Update is PUT via POST + _method so
    // a receipt file can ride along (multipart). ExpensePolicy on every action.
    Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
    Route::post('expenses', [ExpenseController::class, 'store'])->name('expenses.store');
    Route::put('expenses/{expense}', [ExpenseController::class, 'update'])->name('expenses.update');
    Route::post('expenses/{expense}/void', [ExpenseController::class, 'void'])->name('expenses.void');
    Route::get('expenses/{expense}/receipt', [ExpenseController::class, 'receipt'])->name('expenses.receipt');
    Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
    Route::put('expense-categories/{category}', [ExpenseCategoryController::class, 'update'])->name('expense-categories.update');

    // Public lead form management (share link / QR / embed / send). Registered
    // BEFORE Route::resource('leads') so "form" is never bound as a {lead} id.
    Route::get('leads/form', [LeadFormController::class, 'show'])->name('leads.form');
    Route::put('leads/form', [LeadFormController::class, 'update'])->name('leads.form.update');
    Route::post('leads/form/regenerate', [LeadFormController::class, 'regenerate'])->name('leads.form.regenerate');
    Route::post('leads/form/send', [LeadFormController::class, 'send'])->name('leads.form.send');
    Route::get('leads/form/qr', [LeadFormController::class, 'qr'])->name('leads.form.qr');

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

    // Agreement terms templates. Registered BEFORE the agreements resource so
    // "templates" is never bound as an {agreement} id. Templates are read by
    // everyone and written by tenant_admin (AgreementTemplatePolicy); platform
    // defaults are read-only and must be copied before editing.
    Route::get('agreements/templates', [AgreementTemplateController::class, 'index'])
        ->name('agreements.templates');
    Route::post('agreements/templates', [AgreementTemplateController::class, 'store'])
        ->name('agreements.templates.store');
    Route::put('agreements/templates/default-state', [AgreementTemplateController::class, 'updateDefaultState'])
        ->name('agreements.templates.state');
    Route::put('agreements/templates/{template}', [AgreementTemplateController::class, 'update'])
        ->whereNumber('template')->name('agreements.templates.update');
    Route::post('agreements/templates/{template}/copy', [AgreementTemplateController::class, 'copy'])
        ->whereNumber('template')->name('agreements.templates.copy');

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
    // Recovery for a PDF that never generated (queue worker down at signing).
    // Refused when the file already exists — a signed document is not re-rendered.
    Route::post('agreements/{agreement}/pdf', [AgreementController::class, 'rebuildPdf'])
        ->name('agreements.pdf.rebuild');

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
    // Re-render an older invoice with the current template (queued).
    Route::post('invoices/{invoice}/pdf', [InvoiceController::class, 'regeneratePdf'])
        ->name('invoices.pdf.regenerate');

    // Billing portal — tenant_admin only (BillingPolicy via the 'viewBilling' /
    // 'requestUpgrade' / 'manageSubscription' gates in the controllers).
    Route::get('billing', [BillingController::class, 'index'])->name('billing.index');
    Route::post('billing/upgrade-request', [UpgradeRequestController::class, 'store'])
        ->name('billing.upgrade-request');

    // Stripe Checkout (hosted page). {plan} binds by id — Plan is platform-wide
    // (unscoped), so cross-tenant leakage is not a concern; the checkout service
    // still guards active/paid/synced. success/cancel are merely where Stripe
    // sends the browser back — activation ONLY happens via webhook.
    Route::post('billing/checkout/{plan}', [StripeCheckoutController::class, 'checkout'])
        ->name('billing.checkout');
    Route::get('billing/checkout/success', [StripeCheckoutController::class, 'success'])
        ->name('billing.checkout.success');
    Route::get('billing/checkout/cancel', [StripeCheckoutController::class, 'cancel'])
        ->name('billing.checkout.cancel');

    // Cancel the Stripe subscription (at period end — webhook finalises).
    Route::post('billing/cancel', [BillingController::class, 'cancelSubscription'])
        ->name('billing.cancel');

    // Settings hub + the sections that live under it. Each controller enforces
    // its own permissions (most writes are tenant_admin-only); the hub only
    // decides which cards are shown.
    Route::get('settings', [SettingsController::class, 'index'])->name('settings');
    Route::get('settings/activity', [SettingsController::class, 'activity'])->name('settings.activity');

    Route::get('settings/company', [CompanyProfileController::class, 'show'])->name('settings.company');
    Route::put('settings/company', [CompanyProfileController::class, 'update'])->name('settings.company.update');
    Route::post('settings/company/logo', [CompanyProfileController::class, 'updateLogo'])->name('settings.company.logo');
    Route::delete('settings/company/logo', [CompanyProfileController::class, 'removeLogo'])->name('settings.company.logo.remove');

    // Staff — the first way for a rental company to add its own users.
    // {staff}/{invitation} bind through TenantScope (cross-tenant id => 404).
    Route::get('settings/staff', [StaffController::class, 'index'])->name('settings.staff');
    Route::post('settings/staff/invite', [StaffController::class, 'invite'])->name('settings.staff.invite');
    Route::put('settings/staff/{staff}', [StaffController::class, 'update'])->name('settings.staff.update');
    Route::post('settings/staff/invitations/{invitation}/resend', [StaffController::class, 'resendInvitation'])
        ->name('settings.staff.invite.resend');
    Route::delete('settings/staff/invitations/{invitation}', [StaffController::class, 'revokeInvitation'])
        ->name('settings.staff.invite.revoke');

    Route::get('settings/finance', [FinanceSettingsController::class, 'show'])->name('settings.finance');
    Route::put('settings/finance', [FinanceSettingsController::class, 'update'])->name('settings.finance.update');
    Route::get('settings/regional', [RegionalSettingsController::class, 'show'])->name('settings.regional');
    Route::put('settings/regional', [RegionalSettingsController::class, 'update'])->name('settings.regional.update');
    // Settings → Invoices: layout, logo, colour and wording for invoice PDFs.
    // The preview renders the chosen layout as HTML with sample data (a PDF
    // would have to be queued — CLAUDE.md).
    Route::get('settings/invoice-template', [InvoiceTemplateController::class, 'show'])
        ->name('settings.invoice-template');
    Route::put('settings/invoice-template', [InvoiceTemplateController::class, 'update'])
        ->name('settings.invoice-template.update');
    Route::get('settings/invoice-template/preview', [InvoiceTemplateController::class, 'preview'])
        ->name('settings.invoice-template.preview');
    Route::post('settings/invoice-template/logo', [InvoiceTemplateController::class, 'updateLogo'])
        ->name('settings.invoice-template.logo');
    Route::delete('settings/invoice-template/logo', [InvoiceTemplateController::class, 'removeLogo'])
        ->name('settings.invoice-template.logo.remove');

    Route::get('settings/integrations', [IntegrationSettingsController::class, 'show'])->name('settings.integrations');
    Route::put('settings/integrations', [IntegrationSettingsController::class, 'update'])->name('settings.integrations.update');
    // Notification settings — tenant-wide provider selection + channel toggles.
    // Not a resource (single settings page); tenant_admin-gated in the controller.
    Route::get('notifications/settings', [NotificationSettingsController::class, 'edit'])
        ->name('notifications.settings');
    Route::put('notifications/settings', [NotificationSettingsController::class, 'update'])
        ->name('notifications.settings.update');

    // Mechanic accounts — tenant_admin-only CRUD (ManageMechanicPolicy). The
    // workshop portal logins are created here (no more tinker/seeder). No show
    // route (Index lists everything); destroy soft-deletes. {mechanic} binds
    // through TenantScope (cross-tenant id => 404).
    Route::resource('mechanics', MechanicController::class)
        ->except(['show']);

    // Workshop (admin oversight, read-only). Service logs are created/mutated only
    // from the mechanic portal. {log}/{vehicle} bind through TenantScope (404).
    Route::get('workshop', [WorkshopController::class, 'index'])->name('workshop.index');
    Route::get('workshop/vehicle/{vehicle}', [WorkshopController::class, 'vehicleHistory'])
        ->name('workshop.vehicle');
    Route::get('workshop/{log}', [WorkshopController::class, 'show'])->name('workshop.show');

    // AI Assistant — tenant-restricted; HARD-GATED on the 'ai' plan module
    // (tenant.module:ai → 403 if the plan excludes it). Conversations are scoped
    // to the current tenant AND the current user (AiPolicy). {conversation} binds
    // through TenantScope (cross-tenant id => 404).
    //
    // The module middleware is applied PER ROUTE (not via a nested group): a
    // nested ->middleware()->group() here left implicit route-model binding for
    // {conversation} unsubstituted, so the leading {tenant_slug} shifted into the
    // controller's model argument (TypeError). Flat routes bind correctly.
    Route::get('ai', [AiController::class, 'index'])
        ->middleware('tenant.module:ai')->name('ai.index');
    Route::post('ai/new', [AiController::class, 'newConversation'])
        ->middleware('tenant.module:ai')->name('ai.new');
    // chat is async (axios) and returns JSON, not Inertia. Throttled hard —
    // 20/min per tenant user — because AI calls cost money (ai-chat limiter).
    Route::post('ai/chat', [AiController::class, 'chat'])
        ->middleware(['tenant.module:ai', 'throttle:ai-chat'])->name('ai.chat');
    Route::get('ai/{conversation}', [AiController::class, 'show'])
        ->middleware('tenant.module:ai')->name('ai.show');
    Route::delete('ai/{conversation}', [AiController::class, 'destroy'])
        ->middleware('tenant.module:ai')->name('ai.destroy');

    // Reporting & Analytics. All reads go through ReportCacheService (Redis) so
    // the DB is only hit on a cache miss. Exports are queued; their files expire
    // after 24h and download via short-lived signed S3 URLs.
    Route::get('reports', [ReportingController::class, 'dashboard'])->name('reports.index');
    Route::get('reports/revenue', [ReportingController::class, 'revenue'])->name('reports.revenue');
    Route::get('reports/fleet', [ReportingController::class, 'fleet'])->name('reports.fleet');
    Route::get('reports/overdue', [ReportingController::class, 'overdue'])->name('reports.overdue');
    Route::get('reports/workshop', [ReportingController::class, 'workshop'])->name('reports.workshop');
    Route::get('reports/customers', [ReportingController::class, 'customers'])->name('reports.customers');
    Route::get('reports/maintenance', [ReportingController::class, 'maintenance'])->name('reports.maintenance');
    Route::get('reports/expenses', [ReportingController::class, 'expenses'])->name('reports.expenses');

    // Queue an export — 5/hour per tenant (report-export limiter; expensive).
    Route::post('reports/export', [ReportingController::class, 'export'])
        ->middleware('throttle:report-export')->name('reports.export');

    // Download a generated export. {export} binds via TenantScope (cross-tenant
    // id => 404). Declared after the literal report routes to avoid shadowing.
    Route::get('reports/exports/{export}', [ReportingController::class, 'downloadExport'])
        ->name('reports.exports.download');
});
