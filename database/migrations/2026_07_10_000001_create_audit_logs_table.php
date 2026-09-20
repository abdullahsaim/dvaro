<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * APPEND-ONLY audit trail (CLAUDE.md: "Audit logs: append-only,
     * tenant-scoped, store old/new values, IP, timestamp").
     *
     * Actor is stored loosely (type + id + label) rather than as a morphTo:
     * actors span four guards (tenant_user, mechanic, customer, super_admin)
     * and a log must outlive the account that created it.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            // e.g. settings.company_profile.updated, staff.role_changed
            $table->string('action');
            // What was acted on: 'tenant_user', 'settings', 'expense'…
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            // Name/email captured AT THE TIME (the account may later change).
            $table->string('actor_label')->nullable();

            // Only the keys that actually changed.
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();

            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at');

            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
