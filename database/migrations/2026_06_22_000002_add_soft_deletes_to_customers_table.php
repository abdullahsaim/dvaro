<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Customers are NEVER hard-deleted (agreement & ledger history depend on
     * them — agreements.customer_id and ledger_entries.customer_id are both
     * restrictOnDelete). Soft delete is an UPDATE that sets deleted_at, so it
     * never trips those FKs; a customer with history can still be archived out
     * of the active list. Same reasoning as vehicles.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
