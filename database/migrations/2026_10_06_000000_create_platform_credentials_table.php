<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_credentials', function (Blueprint $table) {
            $table->id();

            // One row per credential (stripe_secret, paypal_client_id, smtp_host,
            // ...) — see PlatformCredentialService::KEYS for the fixed set this
            // app understands. Deliberately a SEPARATE table from
            // platform_settings: that table's all() feeds the general settings
            // screen wholesale, and these values must never ride along in that
            // response even encrypted-at-rest, since Inertia would still ship
            // the (admittedly encrypted, but better never exposed) payload to
            // the browser. This table is read ONLY by PlatformCredentialService.
            $table->string('key')->unique();
            // Always Crypt::encryptString()'d, even for non-secret values like
            // paypal_mode — one encryption rule for the whole table is simpler
            // to reason about than a secret/non-secret split.
            $table->text('value')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_credentials');
    }
};
