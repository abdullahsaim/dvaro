<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a free-text notes column to agreements (Agreement Engine session).
 *
 * Nullable: notes are optional internal remarks captured at agreement creation.
 * Versioning copies notes forward like every other field — the column lives on
 * every version row, not a shared parent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
