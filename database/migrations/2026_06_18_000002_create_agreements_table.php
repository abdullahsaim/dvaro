<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreements', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // Agreement may exist before a customer profile in some flows, so
            // nullable. restrictOnDelete: agreements are immutable legal/financial
            // records — a customer must never be deleted while referenced here.
            $table->foreignId('customer_id')->nullable()
                ->constrained('customers')->restrictOnDelete();

            // FK to vehicles deferred — the Fleet module is not built yet.
            // Plain unsignedBigInteger now; FK constraint added in Fleet session.
            $table->unsignedBigInteger('vehicle_id')->nullable();

            // String + model constants (Agreement::TYPE_* / STATUS_*), not DB enums.
            $table->string('type');     // private / delivery / rideshare
            $table->string('status');   // draft / signed / active / completed / cancelled

            // Versioning — immutable append-only history (CLAUDE.md).
            $table->integer('version')->default(1);
            // restrictOnDelete: a parent version must never be deleted while a
            // newer version still references it.
            $table->foreignId('parent_agreement_id')->nullable()
                ->constrained('agreements')->restrictOnDelete();

            $table->string('billing_cycle');            // daily / weekly / monthly
            $table->string('billing_cycle_day')->nullable(); // e.g. "wednesday" or "15"

            $table->integer('rate');                    // cents per billing cycle
            $table->integer('bond_amount')->default(0); // cents

            $table->date('start_date');
            $table->date('end_date')->nullable();       // null = no fixed term

            $table->timestamp('signed_at')->nullable();
            $table->text('signature_data')->nullable(); // canvas signature, base64
            $table->string('pdf_path')->nullable();     // S3 path once generated

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('customer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreements');
    }
};
