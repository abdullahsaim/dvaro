<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Stripe Customer for this tenant. Created lazily on the tenant's
            // FIRST checkout and reused on every subsequent one. Indexed:
            // webhook handling resolves the tenant by this value.
            $table->string('stripe_customer_id')->nullable()->after('trial_ends_at');
            $table->index('stripe_customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropIndex(['stripe_customer_id']);
            $table->dropColumn('stripe_customer_id');
        });
    }
};
