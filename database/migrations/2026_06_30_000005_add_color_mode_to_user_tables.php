<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user dark/light preference (CLAUDE.md: "Dark/Light Mode: Per-user
 * preference, stored in user profile"). One column across all four guard
 * user tables. Value: 'light' | 'dark' | 'system' (default 'system' → follow
 * the OS preference until the user explicitly chooses).
 */
return new class extends Migration
{
    /**
     * The four authenticatable user tables, one per guard.
     */
    private array $tables = [
        'tenant_users',
        'customer_users',
        'mechanics',
        'super_admins',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('color_mode')->default('system');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('color_mode');
            });
        }
    }
};
