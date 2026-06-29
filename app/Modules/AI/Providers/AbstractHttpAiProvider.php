<?php

namespace App\Modules\AI\Providers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Base for the OpenAI-compatible chat-completion providers (Groq, Qwen via
 * DashScope compatible mode, DeepSeek). They share one wire format, so the
 * request/response handling lives here; subclasses only declare their endpoint,
 * model, API key and limits.
 *
 * The system prompt is prepended as a role:'system' message; the rest of the
 * thread (oldest → newest) follows. Tenant data, when present, is already inside
 * $systemPrompt — this layer is tenant-agnostic and never queries the database.
 *
 * CONTRACT (AiProviderInterface): query() NEVER throws. Any transport/API/parse
 * failure is logged and a graceful message string is returned, so a provider
 * outage can never break the chat flow or roll back the persisted user message.
 */
abstract class AbstractHttpAiProvider extends AbstractAiProvider
{
    /** Full chat-completions URL. */
    abstract protected function endpoint(): string;

    /** Model identifier sent in the request body. */
    abstract protected function model(): string;

    /** Bearer API key, or null/'' when unconfigured (the factory gates on this). */
    abstract protected function apiKey(): ?string;

    public function query(array $messages, string $systemPrompt, int $tenantId): string
    {
        $this->lastTokens = null;

        $key = (string) $this->apiKey();

        // Defensive: the factory only resolves a real provider when its key is
        // configured, but never call out with an empty bearer token.
        if ($key === '') {
            Log::warning('AI provider called without an API key', [
                'provider' => $this->name(),
                'tenant_id' => $tenantId,
            ]);

            return __('ai.provider_unavailable');
        }

        $payload = [
            'model' => $this->model(),
            'messages' => array_merge(
                [['role' => 'system', 'content' => $systemPrompt]],
                array_values($messages),
            ),
            'max_tokens' => $this->maxTokens(),
            'temperature' => 0.4,
        ];

        try {
            $response = Http::withToken($key)
                ->acceptJson()
                ->timeout(45)
                ->post($this->endpoint(), $payload);

            if ($response->failed()) {
                Log::error('AI provider request failed', [
                    'provider' => $this->name(),
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return __('ai.provider_error');
            }

            $content = $response->json('choices.0.message.content');
            $this->lastTokens = $response->json('usage.total_tokens');

            if (! is_string($content) || trim($content) === '') {
                Log::error('AI provider returned an empty completion', [
                    'provider' => $this->name(),
                    'tenant_id' => $tenantId,
                ]);

                return __('ai.provider_error');
            }

            return trim($content);
        } catch (Throwable $e) {
            // Never propagate — the user message is already persisted.
            Log::error('AI provider threw', [
                'provider' => $this->name(),
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return __('ai.provider_error');
        }
    }
}
