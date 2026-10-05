<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * APPEND-ONLY platform-wide activity trail — the super-admin-facing
     * counterpart to `audit_logs`, which is tenant-OWNED (HasTenant, non-null
     * tenant_id) and cannot represent an action with no tenant context (a
     * super admin login, a plan created/edited, a settings change that spans
     * every tenant). `tenant_id` here is nullable and NOT a HasTenant FK:
     * most rows concern a specific tenant (suspend, plan assigned,
     * impersonation) but some genuinely have none.
     */
    public function up(): void
    {
        Schema::create('platform_activity_logs', function (Blueprint $table) {
            $table->id();
            // Nullable, unlike audit_logs.tenant_id — a platform-level action
            // (super admin login, plan definition created) has no tenant.
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->nullOnDelete();

            // e.g. tenant.suspended, superadmin.login, plan.created
            $table->string('action');
            $table->string('subject_type')->nullable();
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

            $table->index('created_at');
            $table->index(['tenant_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_activity_logs');
    }
};
