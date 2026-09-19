<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            // restrictOnDelete: categories are hidden, never deleted, while used.
            $table->foreignId('expense_category_id')->constrained('expense_categories')->restrictOnDelete();
            // Optional vehicle link (feeds profit-per-vehicle). Vehicles soft
            // delete, so this never actually nulls in practice.
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();

            $table->date('expense_date');
            $table->string('description');
            $table->string('supplier')->nullable();

            // Money in CENTS. amount_total is GST-inclusive (as printed on
            // Australian receipts); amount_ex_gst = amount_total - gst_amount.
            $table->integer('amount_total');
            $table->integer('gst_amount')->default(0);
            $table->integer('amount_ex_gst');
            $table->boolean('includes_gst')->default(true);

            // cash | card | bank_transfer | other
            $table->string('payment_method');

            // Receipt on the DEFAULT (private) disk — sensitive, signed-URL only.
            $table->string('receipt_path')->nullable();
            $table->text('notes')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('tenant_users')->nullOnDelete();

            // Void instead of delete — no hard deletes; a reversing ledger entry
            // is appended (VoidExpenseAction).
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->string('void_reason')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'expense_date']);
            $table->index(['tenant_id', 'expense_category_id']);
            $table->index(['tenant_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
