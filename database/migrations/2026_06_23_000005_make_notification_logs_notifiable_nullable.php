<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Make the notification_logs notifiable_* columns nullable.
 *
 * Tenant-level triggers (e.g. plan-limit-reached, trial-expiring) have no
 * per-record notifiable — they are about the tenant itself. The original
 * create migration made these NOT NULL; relaxing them lets those events log
 * cleanly without a fake notifiable. Notifications that DO target a record
 * (customer/vehicle/agreement) still populate both columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('notifiable_type')->nullable()->change();
            $table->unsignedBigInteger('notifiable_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table) {
            $table->string('notifiable_type')->nullable(false)->change();
            $table->unsignedBigInteger('notifiable_id')->nullable(false)->change();
        });
    }
};
