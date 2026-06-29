<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_users', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // The profile/ledger record this login account belongs to. A customer
            // may have history (agreements, invoices) so we restrictOnDelete — the
            // login account must be removed before the Customer can ever be hard
            // deleted (Customers are soft-deleted anyway). This is the AUTH account;
            // the Customer model is the profile/ledger record — deliberately split.
            $table->foreignId('customer_id')
                ->constrained('customers')
                ->restrictOnDelete();
            $table->index('customer_id');

            // Email must match the customer's email. Unique PER TENANT, never
            // globally — the same address may identify different customers across
            // different tenants (mirrors tenant_users / mechanics).
            $table->string('email');
            $table->string('password');

            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_users');
    }
};
