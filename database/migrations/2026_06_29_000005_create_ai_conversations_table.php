<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // The tenant user who owns this conversation. nullOnDelete: keep the
            // conversation history if the staff account is later removed.
            $table->foreignId('user_id')->nullable()->constrained('tenant_users')->nullOnDelete();

            // help | intelligence (AiConversation::MODES).
            $table->string('mode');

            // Auto-generated from the first user message; nullable until set.
            $table->string('title')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Conversations are listed per tenant user (the index list query).
            $table->index(['tenant_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_conversations');
    }
};
