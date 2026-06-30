<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();

            // PRE-TENANT lead capture from the public landing site. No tenant_id
            // — these prospects do not belong to any tenant yet. The public
            // contact form also writes here (the enquiry goes in `message`).
            $table->string('company_name');
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->text('message')->nullable();

            // new | contacted | converted — managed from the super admin panel.
            $table->string('status')->default('new');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
