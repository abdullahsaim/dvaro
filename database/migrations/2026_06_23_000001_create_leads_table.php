<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // Contact basics — required at the DB level (a lead is meaningless
            // without a way to reach the person).
            $table->string('name');
            $table->string('email');
            $table->string('phone');

            // Everything the customer fills in on the public intake form. All
            // nullable: they fill in what they can, when they can.
            $table->text('address')->nullable();
            $table->string('licence_number')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->date('rental_start_date')->nullable();
            $table->string('rental_duration')->nullable(); // e.g. "3 weeks", "no fixed term"
            $table->text('notes')->nullable();

            // Lifecycle. new → contacted → converted | expired | rejected.
            $table->string('status')->default('new');

            // Signed-URL token for the public intake form (Str::uuid()).
            $table->string('token')->unique();

            // Lead-level link expiry — distinct from the URL signature. NULL means
            // "never auto-expires"; the link stays valid until manually expired.
            $table->timestamp('token_expires_at')->nullable();
            $table->boolean('expires_manually')->default(false);

            $table->timestamp('submitted_at')->nullable(); // customer filled the form
            $table->timestamp('converted_at')->nullable();  // converted to a customer

            // Keep lead history even if the customer is later (hard) removed.
            $table->foreignId('converted_customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();

            // Tenant staff member who created the lead.
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('tenant_users')
                ->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
