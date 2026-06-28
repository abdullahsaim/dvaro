<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts_used', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // cascadeOnDelete: parts have no meaning without their service log.
            $table->foreignId('service_log_id')->constrained('service_logs')->cascadeOnDelete();

            $table->string('name');
            $table->integer('quantity')->default(1);
            $table->integer('unit_cost'); // cents
            $table->integer('total_cost'); // cents — quantity × unit_cost (auto on save)

            $table->timestamps();

            $table->index(['tenant_id', 'service_log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parts_used');
    }
};
