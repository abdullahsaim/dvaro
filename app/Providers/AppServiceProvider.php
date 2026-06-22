<?php

namespace App\Providers;

use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Policies\LeadPolicy;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Policies\CustomerPolicy;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Fleet\Policies\VehiclePolicy;
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

        // Public intake-form submissions: 5 per hour PER TOKEN (the {token} route
        // segment), not per IP — many customers may legitimately share one IP
        // (office, NAT), and each lead link is its own bucket. Curbs form spam
        // without an authenticated user to throttle on.
        RateLimiter::for('crm-intake', function (Request $request) {
            return Limit::perHour(5)->by((string) $request->route('token'));
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
