<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Allow BUSINESS-LEVEL ledger entries (no customer) — company expenses.
     *
     * Customer balances are always computed WHERE customer_id = ?, so rows with
     * a NULL customer can never affect any customer's balance. A CHECK
     * constraint keeps every non-expense type customer-bound at the DB level.
     */
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });

        DB::statement(
            "ALTER TABLE ledger_entries ADD CONSTRAINT ledger_entries_customer_required
             CHECK (customer_id IS NOT NULL OR type = 'expense')"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledger_entries DROP CONSTRAINT IF EXISTS ledger_entries_customer_required');

        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable(false)->change();
        });
    }
};
