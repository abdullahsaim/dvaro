<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // plan_id already exists (nullable, unindexed) from the tenants
            // migration. Now that plans exists, attach the FK. A deleted plan
            // nulls the pointer rather than removing tenants.
            $table->index('plan_id');
            $table->foreign('plan_id')
                ->references('id')
                ->on('plans')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropForeign(['plan_id']);
            $table->dropIndex(['plan_id']);
        });
    }
};
