<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            // Status is a string + model constants (Tenant::STATUS_*), not a
            // native DB enum — portable across PostgreSQL/SQLite and easy to extend.
            $table->string('status')->default('trial');
            // No FK yet — the plans/SaaS Core module does not exist today.
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamps();

            // Explicit lookup index for slug-based tenant resolution
            // (in addition to the unique constraint above).
            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
