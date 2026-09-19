<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_logs', function (Blueprint $table) {
            // Only a SCHEDULED service resets the vehicle's service schedule on
            // completion (a brake repair must not push the next oil service out).
            $table->boolean('is_scheduled_service')->default(false);
            // The reading was lower than the vehicle's current odometer, so it
            // was kept on the log but NOT recorded as the vehicle's reading.
            $table->boolean('odometer_ignored')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('service_logs', function (Blueprint $table) {
            $table->dropColumn(['is_scheduled_service', 'odometer_ignored']);
        });
    }
};
