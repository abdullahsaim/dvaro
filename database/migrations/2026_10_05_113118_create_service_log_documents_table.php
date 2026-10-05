<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Photos/documents attached to a workshop job (damage photos, parts
     * receipts, inspection sheets). SENSITIVE — stored on the default
     * (private) disk only, served exclusively via FileUrlService signed
     * URLs, same pattern as customer identity documents and expense receipts.
     */
    public function up(): void
    {
        Schema::create('service_log_documents', function (Blueprint $table) {
            $table->id();
            $table->tenantId();

            $table->foreignId('service_log_id')->constrained('service_logs')->cascadeOnDelete();

            $table->string('path');
            $table->string('original_name');
            // Who attached it — mechanics upload from the field; a tenant
            // admin can also attach from the desktop Show page.
            $table->string('uploaded_by_type'); // 'mechanic' | 'tenant_user'
            $table->unsignedBigInteger('uploaded_by_id');

            $table->timestamps();

            $table->index(['tenant_id', 'service_log_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_log_documents');
    }
};
