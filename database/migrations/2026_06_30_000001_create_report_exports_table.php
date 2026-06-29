<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // The tenant user who requested the export. nullOnDelete: keep the
            // export record even if the staff member is later removed.
            $table->foreignId('requested_by')->nullable()
                ->constrained('tenant_users')->nullOnDelete();

            $table->string('report_type');     // revenue / fleet / overdue / workshop
            $table->string('format');          // pdf / excel

            // The report parameters (date range etc.) the file was built from.
            $table->json('parameters')->nullable();

            $table->string('status')->default('pending'); // pending / ready / failed
            $table->string('file_path')->nullable();       // S3 path once ready

            // Export files expire 24h after creation (CLAUDE.md / this session).
            $table->timestamp('expires_at')->nullable();

            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
