<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mechanics', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            $table->string('name');
            // Email is unique PER TENANT, never globally — the same person's
            // email may exist as a different mechanic under a different tenant.
            $table->string('email');
            $table->string('phone')->nullable();

            // Hashed 4-6 digit PIN for quick portal login (shop-floor friendly).
            // Nullable: a mechanic may be set up with a full password only.
            $table->string('pin')->nullable();
            // Full password for web login. Nullable: a mechanic may use the PIN
            // path only. At least one of pin/password is required to ever log in.
            $table->string('password')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Per-tenant email uniqueness — NOT a global unique on email.
            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mechanics');
    }
};
