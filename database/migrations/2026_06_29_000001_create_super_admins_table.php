<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('super_admins', function (Blueprint $table) {
            $table->id();

            // NO tenant_id: super admins are platform-wide and must never be
            // tenant-scoped. The SuperAdmin model deliberately omits HasTenant.
            $table->string('name');
            // Email is GLOBALLY unique (unlike tenant_users / mechanics, which
            // are unique per tenant) — there is only one platform.
            $table->string('email')->unique();
            $table->string('password');

            // platform_owner | billing_manager | support_agent | content_manager
            // Stored as a string + model constants; Spatie roles (guard
            // 'superadmin') are the authoritative authorization source, this
            // column is a convenience/denormalised label.
            $table->string('role');

            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('super_admins');
    }
};
