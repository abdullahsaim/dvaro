<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per reminder actually SENT for a specific due value — the
        // idempotency ledger for FleetReminderService. A new rego date / service
        // km produces a new due_key, so reminders re-arm automatically.
        Schema::create('fleet_reminders', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
            // registration | insurance | service_date | service_km
            $table->string('kind');
            // due_soon | overdue
            $table->string('stage');
            // The due value reminded about: Y-m-d date or km integer as string.
            $table->string('due_key');
            $table->unsignedSmallInteger('recipient_count')->default(0);
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->unique(['vehicle_id', 'kind', 'stage', 'due_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fleet_reminders');
    }
};
