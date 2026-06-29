<?php

namespace App\Contracts;

/**
 * Contract for AI providers (Groq, Qwen, DeepSeek — with a Log fallback).
 *
 * Never call provider SDKs directly: resolve a provider for the tenant via
 * AiProviderFactory and dispatch through this interface (CLAUDE.md provider-
 * switching pattern). The assistant is tenant-restricted — providers receive
 * only the messages and system prompt the AiService has already scoped to the
 * bound tenant; they must never reach across tenants.
 *
 * CONTRACT: query() NEVER throws. On any transport/API error it catches and
 * returns a human-readable error string, so a provider failure can never break
 * the chat flow (the user's message is already persisted by the time we call).
 */
interface AiProviderInterface
{
    /**
     * Send a chat completion request and return the assistant's reply text.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     *         Conversation history in OpenAI chat format (oldest → newest),
     *         each entry ['role' => 'user'|'assistant', 'content' => string].
     * @param  string  $systemPrompt  The system prompt (mode context). For
     *         intelligence mode this already contains the tenant's data summary.
     * @param  int  $tenantId  The bound tenant id (for logging/attribution only;
     *         never used to fetch another tenant's data).
     * @return string  The assistant response, or a graceful error message.
     */
    public function query(array $messages, string $systemPrompt, int $tenantId): string;

    /**
     * The provider's max completion-token limit (model-specific).
     */
    public function maxTokens(): int;
}
