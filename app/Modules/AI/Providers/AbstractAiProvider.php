<?php

namespace App\Modules\AI\Providers;

use App\Contracts\AiProviderInterface;

/**
 * Shared base for all AI providers.
 *
 * Holds the bits every provider exposes beyond the AiProviderInterface contract
 * (which is deliberately just query() + maxTokens()): a stable provider key for
 * attribution and the token count from the most recent call. AiService reads
 * these to persist `provider` and `tokens_used` on the assistant message,
 * recording the provider that ACTUALLY answered (e.g. 'log' when a real
 * provider's credentials are absent and the factory fell back).
 */
abstract class AbstractAiProvider implements AiProviderInterface
{
    /** Total tokens reported by the most recent query() call (null = unknown). */
    protected ?int $lastTokens = null;

    /** Stable provider key stored on the message: groq|qwen|deepseek|log. */
    abstract public function name(): string;

    public function lastTokensUsed(): ?int
    {
        return $this->lastTokens;
    }
}
