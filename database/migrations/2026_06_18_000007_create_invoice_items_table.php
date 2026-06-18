<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            // cascadeOnDelete: items are children of their invoice. If the parent
            // invoice is ever removed (e.g. a draft), its items go with it.
            $table->foreignId('invoice_id')
                ->constrained('invoices')->cascadeOnDelete();

            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            $table->string('description');
            $table->integer('amount'); // cents

            // restrictOnDelete: reporting (utilisation, most-profitable-vehicle)
            // depends on this link surviving — a vehicle must never be deletable
            // while invoice history references it.
            $table->foreignId('vehicle_id')->nullable()
                ->constrained('vehicles')->restrictOnDelete();

            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
