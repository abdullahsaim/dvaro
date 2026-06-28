<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();

            // Platform-wide config — NOT tenant-scoped. One row per setting.
            $table->string('key')->unique();
            // Stored as text; cast on read by the `type` column. Nullable so a
            // setting like default_plan_id can be "unset".
            $table->text('value')->nullable();
            // string | boolean | integer | json — drives the cast in
            // PlatformSettingsService::get().
            $table->string('type')->default('string');
            $table->text('description')->nullable();

            $table->timestamps();
        });

        // Seed the default platform settings. Values are stored as text and
        // cast on read. Booleans as '1'/'0', integers as their decimal string,
        // nullable settings (default_plan_id, max_tenants) left NULL.
        $now = now();

        DB::table('platform_settings')->insert([
            [
                'key' => 'platform_name',
                'value' => 'DVARO',
                'type' => 'string',
                'description' => 'Display name of the platform.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'support_email',
                'value' => 'support@dvaro.com.au',
                'type' => 'string',
                'description' => 'Platform support contact address.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'manual_tenant_approval',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Require a super admin to approve new tenant signups.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'free_trial_enabled',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Offer a free trial on signup.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'free_trial_days',
                'value' => '14',
                'type' => 'integer',
                'description' => 'Length of the free trial in days.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'freemium_enabled',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Allow a permanent free (freemium) tier.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'default_plan_id',
                'value' => null,
                'type' => 'integer',
                'description' => 'Plan a tenant falls back to when no paid plan is available.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'max_tenants',
                'value' => null,
                'type' => 'integer',
                'description' => 'Hard cap on total tenants (null = unlimited).',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Platform-wide maintenance mode.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
