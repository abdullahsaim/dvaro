<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // Keep billing history even if a plan is deleted — restrict, not cascade.
            $table->foreignId('plan_id')->constrained('plans')->restrictOnDelete();

            // String + model constants (Subscription::STATUS_*), not a DB enum.
            $table->string('status');
            $table->string('gateway')->nullable();
            $table->string('gateway_subscription_id')->nullable();
            $table->string('billing_cycle');

            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('gateway_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
