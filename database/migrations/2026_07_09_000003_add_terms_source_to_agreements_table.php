<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // The template body EXACTLY as it read when this agreement was
            // created (merge fields still unresolved). terms_html is this text
            // with the values filled in and frozen.
            //
            // Why both: a NEW VERSION must keep the wording the customer
            // originally agreed to while re-filling the changed details (e.g. a
            // swapped vehicle). Templates are edited in place, so the old
            // wording would otherwise be unrecoverable.
            $table->text('terms_source')->nullable()->after('terms_html');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('terms_source');
        });
    }
};
