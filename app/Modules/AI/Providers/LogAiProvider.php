<?php

namespace App\Modules\AI\Providers;

/**
 * Null-object AI provider. Returned by AiProviderFactory whenever the tenant has
 * not selected a provider, or the selected provider's API key is absent. Mirrors
 * the Notification module's Log* fallback pattern: the assistant is wired and
 * usable end-to-end, but answers with a fixed "not configured" notice until real
 * credentials land in .env — no code change required to activate a provider.
 *
 * Never calls out; reports no tokens.
 */
class LogAiProvider extends AbstractAiProvider
{
    public function name(): string
    {
        return 'log';
    }

    public function maxTokens(): int
    {
        return 0;
    }

    public function query(array $messages, string $systemPrompt, int $tenantId): string
    {
        return __('ai.not_configured');
    }
}
