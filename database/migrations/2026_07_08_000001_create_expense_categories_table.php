<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The client-named defaults every tenant gets (system_key => name). */
    private const DEFAULTS = [
        'daily' => ['Daily expenses', 1],
        'government' => ['Government fees', 2],
        'utilities' => ['Utilities', 3],
    ];

    public function up(): void
    {
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->tenantId();
            $table->string('name');
            // daily | government | utilities for the built-in defaults (can be
            // renamed / hidden, never deleted); NULL for tenant-created ones.
            $table->string('system_key')->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->unique(['tenant_id', 'system_key']);
        });

        // Backfill the defaults for EXISTING tenants. Raw query builder is used
        // deliberately: migrations run with no bound tenant, so the tenant-scoped
        // Eloquent model can't be used here. New tenants get them at onboarding
        // (ExpenseCategoryService::ensureDefaults).
        $now = now();
        $rows = [];
        foreach (DB::table('tenants')->pluck('id') as $tenantId) {
            foreach (self::DEFAULTS as $key => [$name, $order]) {
                $rows[] = [
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'system_key' => $key,
                    'is_hidden' => false,
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('expense_categories')->insertOrIgnore($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('expense_categories');
    }
};
