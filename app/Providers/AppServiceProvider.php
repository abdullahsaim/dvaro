<?php

namespace App\Providers;

use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Policies\AiPolicy;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Policies\AgreementPolicy;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Policies\LeadPolicy;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Policies\CustomerPolicy;
use App\Modules\Customer\Policies\CustomerPortalPolicy;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Policies\VehiclePolicy;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Policies\InvoicePolicy;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Policies\ReportingPolicy;
use App\Modules\SuperAdmin\Policies\SuperAdminPolicy;
use App\Modules\Workshop\Models\ServiceLog;
use App\Modules\Workshop\Policies\MechanicPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Policies in app/Modules/** are NOT auto-discovered (Laravel only looks
        // in App\Policies for App\Models). Register module policies explicitly.
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Agreement::class, AgreementPolicy::class);
        Gate::policy(Invoice::class, InvoicePolicy::class);
        // Workshop service logs are authorized against the MECHANIC guard.
        Gate::policy(ServiceLog::class, MechanicPolicy::class);
        // AI conversations: own-conversation ownership (within tenant) — AiPolicy.
        Gate::policy(AiConversation::class, AiPolicy::class);
        // Reporting: viewAny/export role-free + download own-tenant export.
        Gate::policy(ReportExport::class, ReportingPolicy::class);

        // Super admin role gates. These are role checks (no model argument), so
        // they are registered as Gates delegating to SuperAdminPolicy methods —
        // not via Gate::policy, which would expect a model instance. Controllers
        // call Gate::forUser(auth('superadmin')->user())->authorize('<ability>').
        Gate::define('platformOwner', [SuperAdminPolicy::class, 'platformOwner']);
        Gate::define('billingAccess', [SuperAdminPolicy::class, 'billingAccess']);
        Gate::define('supportAccess', [SuperAdminPolicy::class, 'supportAccess']);
        Gate::define('contentAccess', [SuperAdminPolicy::class, 'contentAccess']);

        // Customer Portal abilities (customer guard). Registered as Gates rather
        // than Gate::policy because Invoice/Agreement already map to their own
        // policies. Controllers call
        // Gate::forUser(auth('customer')->user())->authorize('viewPortalInvoice'…).
        Gate::define('viewPortalInvoice', [CustomerPortalPolicy::class, 'viewInvoice']);
        Gate::define('viewPortalAgreement', [CustomerPortalPolicy::class, 'viewAgreement']);
        Gate::define('makePortalPayment', [CustomerPortalPolicy::class, 'makePayment']);

        // Public intake-form submissions: 5 per hour PER TOKEN (the {token} route
        // segment), not per IP — many customers may legitimately share one IP
        // (office, NAT), and each lead link is its own bucket. Curbs form spam
        // without an authenticated user to throttle on.
        RateLimiter::for('crm-intake', function (Request $request) {
            return Limit::perHour(5)->by((string) $request->route('token'));
        });

        // AI chat: 20 requests per minute PER TENANT USER. AI calls cost money,
        // so this is throttled aggressively. Keyed by tenant + tenant-guard user
        // so one tenant's usage never eats into another's bucket. Falls back to
        // the request IP if (unexpectedly) unauthenticated.
        RateLimiter::for('ai-chat', function (Request $request) {
            $userId = auth('tenant')->id();
            $tenantId = app()->bound('current_tenant') ? app('current_tenant')->id : 'none';

            $key = $userId !== null ? "{$tenantId}:{$userId}" : $request->ip();

            return Limit::perMinute(20)->by((string) $key);
        });

        // Report exports: 5 per hour PER TENANT. Generating a report file (PDF or
        // Excel, queued) is expensive on the VPS, so the export endpoint is capped
        // per tenant — keyed by the bound tenant id so one tenant's exports never
        // eat into another's bucket. IP fallback if (unexpectedly) unbound.
        RateLimiter::for('report-export', function (Request $request) {
            $tenantId = app()->bound('current_tenant') ? app('current_tenant')->id : null;

            return Limit::perHour(5)->by($tenantId !== null ? "tenant:{$tenantId}" : $request->ip());
        });

        // Public landing forms: 3 per hour PER IP. The demo-request and contact
        // forms are unauthenticated and pre-tenant, so there is nothing to key on
        // but the request IP. Curbs marketing-form spam.
        RateLimiter::for('public-forms', function (Request $request) {
            return Limit::perHour(3)->by($request->ip());
        });

        // Reusable migration helper: add a tenant_id column + index in one line.
        //
        //   Schema::create('vehicles', function (Blueprint $table) {
        //       $table->id();
        //       $table->tenantId();   // unsignedBigInteger + FK to tenants + index
        //       ...
        //   });
        //
        // PostgreSQL does not auto-index foreign keys, so the index is explicit.
        Blueprint::macro('tenantId', function () {
            /** @var Blueprint $this */
            $this->foreignId('tenant_id')
                ->constrained('tenants')
                ->cascadeOnDelete();

            $this->index('tenant_id');
        });
    }
}
