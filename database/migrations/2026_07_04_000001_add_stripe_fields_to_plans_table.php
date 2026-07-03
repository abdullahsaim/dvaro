<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            // Stripe Product + Prices mirroring this plan. Populated (and kept
            // current) by StripeSyncService / `php artisan stripe:sync-plans`.
            // Nullable: free plans and never-synced plans have none.
            $table->string('stripe_product_id')->nullable()->after('sort_order');
            $table->string('stripe_monthly_price_id')->nullable()->after('stripe_product_id');
            $table->string('stripe_annual_price_id')->nullable()->after('stripe_monthly_price_id');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_product_id',
                'stripe_monthly_price_id',
                'stripe_annual_price_id',
            ]);
        });
    }
};
