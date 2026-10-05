<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // PayPal Product + billing Plans mirroring this plan. Populated
            // (and kept current) by PaypalSyncService / `php artisan paypal:sync-plans`.
            // Nullable: free plans and never-synced plans have none.
            $table->string('paypal_product_id')->nullable()->after('stripe_annual_price_id');
            $table->string('paypal_monthly_plan_id')->nullable()->after('paypal_product_id');
            $table->string('paypal_annual_plan_id')->nullable()->after('paypal_monthly_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'paypal_product_id',
                'paypal_monthly_plan_id',
                'paypal_annual_plan_id',
            ]);
        });
    }
};
