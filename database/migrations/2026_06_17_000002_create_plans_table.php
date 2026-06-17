<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            // Monetary values are ALWAYS integer cents — never decimals.
            $table->integer('price_monthly')->default(0);
            $table->integer('price_annual')->default(0);

            $table->boolean('is_active')->default(true);
            $table->boolean('is_free')->default(false);
            $table->integer('trial_days')->default(14);

            // Enabled module keys, e.g. ["fleet","rental","invoice"].
            $table->json('modules')->nullable();
            // Hard limits, e.g. {"max_vehicles":10,"max_staff":5,
            // "max_storage_gb":20,"max_file_size_mb":10}. Absent key = unlimited.
            $table->json('limits')->nullable();

            $table->integer('sort_order')->default(0);
            $table->timestamps();

            // Plans are platform-wide (no tenant scope). Listing pages filter
            // on availability, so index the active flag.
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
