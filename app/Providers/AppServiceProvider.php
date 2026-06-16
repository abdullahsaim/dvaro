<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
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
