<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // The subscription this payment settles. restrictOnDelete: a
            // subscription with recorded payments must not be deleted.
            $table->foreignId('subscription_id')
                ->constrained('subscriptions')->restrictOnDelete();

            // Amount in CENTS (integer, never float).
            $table->integer('amount');

            // Per-tenant currency (AUD default). Kept per row so historical
            // records are self-describing even if the tenant currency changes.
            $table->string('currency')->default('AUD');

            // bank_transfer / cash / stripe / paypal / other
            // (SubscriptionPayment::METHODS). Offline methods only this session.
            $table->string('method');

            // Bank reference / receipt number etc. — null for cash.
            $table->string('reference')->nullable();

            $table->text('notes')->nullable();

            $table->timestamp('paid_at');

            // The super admin who recorded it. nullOnDelete: keep the payment
            // record even if the super admin account is later removed.
            $table->foreignId('recorded_by')->nullable()
                ->constrained('super_admins')->nullOnDelete();

            $table->timestamps();

            $table->index(['tenant_id', 'subscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
    }
};
