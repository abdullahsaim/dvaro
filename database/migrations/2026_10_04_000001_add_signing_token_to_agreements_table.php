<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // Secret token in the customer's PUBLIC review-and-sign link
            // (emailed/WhatsApped from the agreement page). Written only by
            // AgreementSigningService — never mass-assigned. Stays valid (and
            // the same link keeps working) after signing, so "view my signed
            // agreement" from the confirmation email is the same URL; the
            // page itself decides what to show by the agreement's status.
            $table->string('signing_token', 64)->nullable()->unique();
            // When the link was last sent — drives the "sent 3 days ago, with
            // a Resend option" state on the agreement page. Null = never sent
            // (in-person signing is still the default path).
            $table->timestamp('signing_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropUnique(['signing_token']);
            $table->dropColumn(['signing_token', 'signing_sent_at']);
        });
    }
};
