<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // restrictOnDelete: the ledger is the system of record — a customer
            // must never be deleted while financial history references them.
            $table->foreignId('customer_id')
                ->constrained('customers')->restrictOnDelete();

            // String + model constants (LedgerEntry::TYPE_*), not a DB enum.
            // rental_charge / payment / late_fee / discount / bond_collection /
            // bond_refund / bond_deduction / expense / refund.
            $table->string('type');

            // AMOUNT IS IN CENTS (integer, never float/decimal).
            //
            // SIGN CONVENTION — this is the financial source of truth, get it right:
            //   POSITIVE  = debit / charge  → customer owes MORE
            //               (rental_charge, late_fee, expense, bond_collection,
            //                bond_deduction)
            //   NEGATIVE  = credit / payment → customer owes LESS
            //               (payment, discount, bond_refund, refund)
            //
            // A customer's balance is simply SUM(amount) over their entries:
            //   positive net = customer owes the tenant; negative net = in credit.
            $table->integer('amount');

            // Optional polymorphic-style pointer to the originating record
            // (e.g. reference_type 'invoice' / 'agreement', reference_id = its id).
            // Not a real morph relation — kept loose so any module can reference it.
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();

            $table->string('description');

            // null = system-generated (automation), otherwise the acting user.
            // nullOnDelete: keep the immutable entry even if the user is removed.
            $table->foreignId('created_by')->nullable()
                ->constrained('users')->nullOnDelete();

            // Append-only: the ledger is NEVER updated. updated_at exists for
            // column-convention consistency but is never written — the model
            // sets `const UPDATED_AT = null`.
            $table->timestamps();

            $table->index(['tenant_id', 'customer_id']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
    }
};
