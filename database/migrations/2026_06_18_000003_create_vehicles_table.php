<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // Unique per tenant (enforced by the composite unique index below),
            // not globally — two tenants may legitimately share a plate string.
            $table->string('registration_number');

            $table->string('make');
            $table->string('model');
            $table->integer('year');

            // String + model constants (Vehicle::STATUS_*), not a DB enum.
            // available / rented / maintenance / suspended / accident / reserved
            $table->string('status')->default('available');

            $table->integer('daily_rate'); // cents

            $table->string('insurance_company')->nullable();
            $table->date('insurance_expiry')->nullable();
            $table->date('registration_expiry')->nullable();

            $table->date('last_service_date')->nullable();
            $table->date('next_service_due')->nullable();

            // Signed token used to resolve a vehicle from its QR code. Generated
            // in a later (Workshop/QR) session — nullable for now, globally
            // unique because QR resolution happens before a tenant is bound.
            $table->string('qr_code_token')->nullable()->unique();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Registration number is unique within a tenant, not across the platform.
            $table->unique(['tenant_id', 'registration_number']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
