<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // restrictOnDelete: invoices are financial records — neither the
            // customer nor the originating agreement may be deleted while an
            // invoice references them.
            $table->foreignId('customer_id')
                ->constrained('customers')->restrictOnDelete();
            $table->foreignId('agreement_id')->nullable()
                ->constrained('agreements')->restrictOnDelete();

            // String + model constants (Invoice::TYPE_* / STATUS_*), not DB enums.
            $table->string('type');              // recurring / manual / prorated
            $table->string('status')->default('draft'); // draft/sent/paid/overdue/cancelled

            $table->date('billing_period_start');
            $table->date('billing_period_end');
            $table->date('due_date');

            // All amounts in CENTS (integer, never float/decimal).
            $table->integer('subtotal');
            $table->integer('total');
            $table->integer('paid_amount')->default(0);

            $table->string('pdf_path')->nullable(); // S3 path once queued PDF runs

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
