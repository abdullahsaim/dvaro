<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Vehicles are NEVER hard-deleted (agreement history depends on them — the
     * agreements.vehicle_id FK is restrictOnDelete). Soft delete is an UPDATE
     * that sets deleted_at, so it never trips that FK, and a vehicle with
     * agreement history can still be archived out of the active fleet.
     */
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
