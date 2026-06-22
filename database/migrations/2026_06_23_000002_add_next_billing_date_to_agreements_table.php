<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            // The date on which the NEXT recurring invoice is due. Set when the
            // agreement is signed (AgreementService::sign) and advanced after each
            // invoice is generated (RecurringInvoiceService::generateDue). Nullable
            // — an unsigned draft has no billing schedule yet. This is operational
            // billing metadata, NOT a term of the agreement, so writing it does not
            // breach agreement immutability (same category as pdf_path).
            $table->date('next_billing_date')->nullable()->after('end_date');

            // generateDue() scans across tenants for due agreements; index the
            // column it filters on.
            $table->index(['tenant_id', 'next_billing_date']);
        });
    }

    public function down(): void
    {
        Schema::table('agreements', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'next_billing_date']);
            $table->dropColumn('next_billing_date');
        });
    }
};
