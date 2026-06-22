<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_users', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            $table->string('name');
            // Email is unique PER TENANT, never globally — the same person's
            // email may exist as a different user under a different tenant.
            $table->string('email');
            $table->string('password');

            // tenant_admin | tenant_staff | tenant_accounts. Spatie roles are the
            // authorisation source of truth; this column is a denormalised hint.
            $table->string('role');

            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Per-tenant email uniqueness — NOT a global unique on email.
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_users');
    }
};
