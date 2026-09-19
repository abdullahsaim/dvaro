<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            // Latest odometer (km). SYSTEM-maintained — written only by
            // RecordOdometerReadingAction (history in odometer_readings).
            $table->unsignedInteger('current_odometer')->nullable();
            $table->timestamp('odometer_updated_at')->nullable();

            // Service schedule: whichever of months / km comes first.
            $table->unsignedSmallInteger('service_interval_months')->nullable();
            $table->unsignedInteger('service_interval_km')->nullable();
            $table->unsignedInteger('last_service_odometer')->nullable();
            // Derived: last_service_odometer + service_interval_km.
            $table->unsignedInteger('next_service_km')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'current_odometer', 'odometer_updated_at', 'service_interval_months',
                'service_interval_km', 'last_service_odometer', 'next_service_km',
            ]);
        });
    }
};
