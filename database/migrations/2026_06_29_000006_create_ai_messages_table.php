<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            // tenant_id: unsignedBigInteger + FK to tenants (cascadeOnDelete) + index.
            $table->tenantId();

            // cascadeOnDelete: messages are owned by their conversation. A
            // conversation soft-deletes (kept), but a hard delete cascades here.
            $table->foreignId('conversation_id')->constrained('ai_conversations')->cascadeOnDelete();

            // user | assistant (AiMessage::ROLES).
            $table->string('role');
            $table->text('content');

            // API usage tracking — nullable (the Log provider reports neither).
            $table->integer('tokens_used')->nullable();
            // Which provider answered (groq|qwen|deepseek|log); null for user rows.
            $table->string('provider')->nullable();

            $table->timestamps();

            // Message history is loaded per conversation (the chat thread query).
            $table->index(['tenant_id', 'conversation_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
