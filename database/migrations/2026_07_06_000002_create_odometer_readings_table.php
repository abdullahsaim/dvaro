<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // APPEND-ONLY odometer history. Rows are never updated or deleted.
        Schema::create('odometer_readings', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->foreignId('vehicle_id')->constrained('vehicles')->restrictOnDelete();
            $table->unsignedInteger('reading'); // km

            // manual | service_log | return_inspection
            $table->string('source');
            // Loose actor reference (tenant_user | mechanic) — spans guards.
            $table->string('recorded_by_type')->nullable();
            $table->unsignedBigInteger('recorded_by_id')->nullable();
            $table->foreignId('service_log_id')->nullable()->constrained('service_logs')->nullOnDelete();

            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['tenant_id', 'vehicle_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odometer_readings');
    }
};
