<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agreement_templates', function (Blueprint $table) {
            $table->id();

            // NULLABLE tenant_id — this table is a HYBRID:
            //   tenant_id set  → a rental company's own template
            //   tenant_id NULL → a PLATFORM DEFAULT, managed by the super admin
            // (hence a manual column instead of the tenantId() macro).
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->string('name');
            // private | delivery | rideshare, or NULL = any agreement type.
            $table->string('agreement_type')->nullable();
            // NSW|VIC|QLD|WA|SA|TAS|ACT|NT, or NULL = all states.
            $table->string('state', 3)->nullable();

            // Sanitised HTML (server-side allow-list) with {{merge.fields}}.
            $table->text('body_html');
            // Bumped on every save; stamped onto agreements for traceability.
            $table->unsignedInteger('revision')->default(1);
            // Archived (never deleted — historic agreements reference it).
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['tenant_id', 'agreement_type', 'state']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agreement_templates');
    }
};
