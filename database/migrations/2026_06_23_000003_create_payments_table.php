<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // restrictOnDelete: a payment is a financial record — neither the
            // invoice it settles nor the customer who paid may be deleted while a
            // payment references them.
            $table->foreignId('invoice_id')
                ->constrained('invoices')->restrictOnDelete();
            $table->foreignId('customer_id')
                ->constrained('customers')->restrictOnDelete();

            // Amount in CENTS (integer, never float).
            $table->integer('amount');

            // cash / bank_transfer / stripe / paypal (Payment::METHODS). Manual
            // methods only this session; gateway methods land later.
            $table->string('method');

            // Set when a gateway (Stripe/PayPal) processed the payment — null for
            // manually recorded cash/bank transfers.
            $table->string('gateway_payment_id')->nullable();

            $table->text('notes')->nullable();

            // The tenant user who recorded it. nullOnDelete: keep the payment
            // record even if the staff member is later removed.
            $table->foreignId('recorded_by')->nullable()
                ->constrained('tenant_users')->nullOnDelete();

            $table->timestamp('paid_at');

            $table->timestamps();

            $table->index(['tenant_id', 'invoice_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
