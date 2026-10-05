<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A future-dated booking, distinct from `is_scheduled_service` (which
     * classifies a job's TYPE for completion-time service-schedule resets,
     * not when it happens). NULL = an ad-hoc job created and started now
     * (today's only path, via CreateServiceLogAction). Set = booked ahead by
     * ScheduleServiceAction — the vehicle stays in service until the day work
     * actually starts (ChangeServiceLogStatusAction flips it to maintenance
     * only on the pending→in_progress transition).
     */
    public function up(): void
    {
        Schema::table('service_logs', function (Blueprint $table) {
            $table->timestamp('scheduled_for')->nullable()->after('mechanic_id');
            $table->index(['tenant_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::table('service_logs', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'scheduled_for']);
            $table->dropColumn('scheduled_for');
        });
    }
};
