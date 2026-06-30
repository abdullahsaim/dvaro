<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shared password-reset token store for the THREE tenant-scoped portal guards
 * (tenant, customer, mechanic). Mirrors Laravel's default password_reset_tokens
 * schema, but the `email` primary key holds a COMPOSITE key, not a bare address:
 *
 *     {guard}:{tenant_id}:{email}     e.g.  tenant:7:owner@acme.test
 *
 * Each portal model's getEmailForPasswordReset() returns this composite (see the
 * ResetsPasswordWithinTenant trait). That makes tokens unique per guard AND per
 * tenant in this one table: a token issued for tenant A's owner@x can never be
 * found — let alone redeemed — under tenant B, even though both share the literal
 * address. The base App\Models\User broker keeps the stock password_reset_tokens
 * table; this is a separate store the portal brokers point at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary(); // composite "{guard}:{tenant_id}:{email}"
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_password_reset_tokens');
    }
};
