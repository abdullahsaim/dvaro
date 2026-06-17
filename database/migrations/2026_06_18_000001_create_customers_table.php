<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->date('date_of_birth')->nullable();

            // licence_number / passport_number are encrypted at the application
            // layer (Customer::casts() => 'encrypted'). Ciphertext is far longer
            // than the plaintext, so these are text columns, not fixed-width strings.
            $table->text('licence_number');
            $table->date('licence_expiry')->nullable();
            $table->text('passport_number')->nullable();

            $table->text('address')->nullable();
            $table->string('emergency_contact_name');
            $table->string('emergency_contact_phone');
            $table->text('risk_notes')->nullable();

            $table->boolean('is_blacklisted')->default(false);
            $table->text('blacklisted_reason')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'is_blacklisted']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
