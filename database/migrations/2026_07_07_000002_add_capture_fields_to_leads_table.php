<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            // link (staff-created per-lead intake link) | public_form | embed
            $table->string('source')->default('link');
            // The website page the embedded form was on (abuse triage / marketing).
            $table->string('referrer_url', 2048)->nullable();
            // IPv4/IPv6 of the public submission (abuse triage only).
            $table->string('submitted_ip', 45)->nullable();
            // passed | unverified (captcha not configured / Google unreachable).
            // NULL for staff-created leads (no captcha involved).
            $table->string('captcha_status')->nullable();

            $table->index(['tenant_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'source']);
            $table->dropColumn(['source', 'referrer_url', 'submitted_ip', 'captcha_status']);
        });
    }
};
