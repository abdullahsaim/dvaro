<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_portal_invitations', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // The customer this invitation grants portal access to. restrictOnDelete:
            // an invitation references a real customer; remove invitations first.
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->index('customer_id');

            $table->string('email');

            // Unguessable signed token (Str::uuid). Globally unique — the public
            // accept route still ALSO matches on explicit tenant_id, so a token
            // from tenant A can never be redeemed on tenant B's portal URL.
            $table->string('token')->unique();
            $table->index('token');

            $table->timestamp('accepted_at')->nullable();
            // Invitations expire 7 days after creation (set by the action).
            $table->timestamp('expires_at');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_portal_invitations');
    }
};
