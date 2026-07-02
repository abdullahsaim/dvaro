<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upgrade_requests', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // The plan the tenant wants. restrictOnDelete: a plan referenced by a
            // pending request must not be deleted out from under it.
            $table->foreignId('requested_plan_id')
                ->constrained('plans')->restrictOnDelete();

            // The plan the tenant was on when they asked (nullable — a tenant with
            // no active plan can still request one). restrictOnDelete for history.
            $table->foreignId('current_plan_id')->nullable()
                ->constrained('plans')->restrictOnDelete();

            // String + model constants (UpgradeRequest::STATUS_*), not a DB enum.
            $table->string('status')->default('pending');

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_requests');
    }
};
