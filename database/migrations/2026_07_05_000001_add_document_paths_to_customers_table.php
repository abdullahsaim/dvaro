<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Storage PATHS (not files) on the default — sensitive — disk:
            // tenants/{tenant_id}/customers/{customer_id}/{type}-{uuid}.{ext}.
            // Written ONLY by UploadCustomerDocumentAction; served only via an
            // auth-checked controller action → short-lived signed URL.
            $table->string('license_front_path')->nullable();
            $table->string('license_back_path')->nullable();
            $table->string('proof_of_address_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['license_front_path', 'license_back_path', 'proof_of_address_path']);
        });
    }
};
