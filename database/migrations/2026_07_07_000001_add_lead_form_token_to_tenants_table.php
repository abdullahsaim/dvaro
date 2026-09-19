<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            // Secret token in the tenant's PUBLIC lead-form URL (share link,
            // QR, website embed). Regenerating it kills every old link/embed.
            // Written only by LeadFormService (never mass-assigned).
            $table->string('lead_form_token', 64)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropUnique(['lead_form_token']);
            $table->dropColumn('lead_form_token');
        });
    }
};
