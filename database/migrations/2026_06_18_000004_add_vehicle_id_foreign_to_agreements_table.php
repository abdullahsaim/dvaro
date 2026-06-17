<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // vehicle_id already exists (nullable, unindexed) from the agreements
            // migration, where the FK was deferred until the Fleet module existed.
            // Now that vehicles exists, attach the FK + index.
            //
            // restrictOnDelete: a vehicle must never be deleted while agreement
            // history references it — agreements are immutable legal/financial
            // records (CLAUDE.md).
            $table->index('vehicle_id');
            $table->foreign('vehicle_id')
                ->references('id')
                ->on('vehicles')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropForeign(['vehicle_id']);
            $table->dropIndex(['vehicle_id']);
        });
    }
};
