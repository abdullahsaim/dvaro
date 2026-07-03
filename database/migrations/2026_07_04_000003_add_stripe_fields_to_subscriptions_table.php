<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // The Stripe SUBSCRIPTION id lives in the pre-existing
            // gateway_subscription_id column (with gateway='stripe') — no
            // duplicate column. These two are the Stripe-specific extras:
            // the exact Price purchased, and Stripe's OWN lifecycle status
            // (active/past_due/canceled/trialing/…), kept separate from the
            // DVARO status column.
            $table->string('stripe_price_id')->nullable()->after('gateway_subscription_id');
            $table->string('stripe_status')->nullable()->after('stripe_price_id');

            // NB: gateway_subscription_id (the webhook lookup key) is already
            // indexed by the original create_subscriptions_table migration.
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['stripe_price_id', 'stripe_status']);
        });
    }
};
