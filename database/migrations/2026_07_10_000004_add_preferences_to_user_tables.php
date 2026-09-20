<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user preferences (landing page, rows per page, personal opt-outs) for the
 * three remaining guards — tenant_users already got the column alongside
 * is_active/last_login_at.
 *
 * Nullable JSON: an untouched account has no row of settings at all and simply
 * falls back to the defaults in App\Services\UserPreferences.
 */
return new class extends Migration
{
    private array $tables = [
        'customer_users',
        'mechanics',
        'super_admins',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'preferences')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->json('preferences')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('preferences');
            });
        }
    }
};
