<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Staff invitations: the invitee sets their own password from an emailed
     * link (mirrors customer_portal_invitations). The token is an unguessable
     * UUID; the public accept route resolves it scope-free by token AND an
     * explicit tenant_id, because no tenant is bound on a public route.
     */
    public function up(): void
    {
        Schema::create('tenant_user_invitations', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->string('name');
            $table->string('email');
            $table->string('role');
            $table->string('token')->unique();

            $table->foreignId('invited_by')->nullable()->constrained('tenant_users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_user_invitations');
    }
};
