<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_logs', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // restrictOnDelete: a vehicle/mechanic with service history can never
            // be hard-deleted out from under its logs (both use soft deletes).
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->foreignId('mechanic_id')->constrained('mechanics')->restrictOnDelete();

            // pending | in_progress | completed | waiting_for_parts | re_inspection_required
            $table->string('status');
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('odometer_reading')->nullable(); // km

            // Money in CENTS. total_cost = labour_cost + sum(parts).
            $table->integer('labour_cost')->default(0);
            $table->integer('total_cost')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'vehicle_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_logs');
    }
};
