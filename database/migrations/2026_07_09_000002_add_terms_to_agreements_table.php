<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // Australian state the agreement is governed by (drives template
            // selection); NULL on agreements created before templates existed.
            $table->string('state', 3)->nullable();

            // Which template (and which revision of it) produced the terms.
            $table->foreignId('agreement_template_id')->nullable()
                ->constrained('agreement_templates')->nullOnDelete();
            $table->unsignedInteger('template_revision')->nullable();

            // THE FROZEN TERMS: merge fields already resolved, sanitised HTML.
            // Written once at creation and NEVER updated — editing a template
            // must never change an agreement a customer already signed.
            $table->text('terms_html')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropConstrainedForeignId('agreement_template_id');
            $table->dropColumn(['state', 'template_revision', 'terms_html']);
        });
    }
};
