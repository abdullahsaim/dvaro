<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_users', function (Blueprint $table) {
            // Deactivated staff keep their history but cannot sign in.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            // Per-user preferences (landing page, rows per page, opt-outs).
            $table->json('preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenant_users', function (Blueprint $table) {
            $table->dropColumn(['is_active', 'last_login_at', 'preferences']);
        });
    }
};
