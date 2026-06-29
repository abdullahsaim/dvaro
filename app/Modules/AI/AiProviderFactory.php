<?php

namespace App\Modules\AI;

use App\Contracts\AiProviderInterface;
use App\Modules\AI\Providers\DeepSeekAiProvider;
use App\Modules\AI\Providers\GroqAiProvider;
use App\Modules\AI\Providers\LogAiProvider;
use App\Modules\AI\Providers\QwenAiProvider;
use App\Modules\SaasCore\Models\Tenant;

/**
 * Resolves the concrete AI provider for a tenant per the provider-switching
 * pattern (CLAUDE.md): callers never new-up a provider directly.
 *
 * Selection is driven by tenant->settings['ai_provider'] (seeded to 'log' on
 * onboarding). CRITICAL fallback rule, mirroring NotificationProviderFactory: a
 * real provider is only returned when its API key is actually configured —
 * otherwise we fall back to LogAiProvider. This realises "Log by default; a real
 * provider activates the moment its key lands in .env" with zero code change,
 * and guarantees we never call a paid AI endpoint with an empty bearer token.
 *
 * Providers themselves are tenant-agnostic; tenant data (intelligence mode) is
 * assembled by IntelligenceModeContext and passed in as the system prompt. The
 * factory never leaks one tenant's configuration into another's resolution.
 */
class AiProviderFactory
{
    public function make(Tenant $tenant): AiProviderInterface
    {
        $choice = (string) ($tenant->settings['ai_provider'] ?? 'log');

        return match ($choice) {
            'groq' => $this->filled(config('services.groq.key'))
                ? new GroqAiProvider()
                : new LogAiProvider(),
            'qwen' => $this->filled(config('services.qwen.key'))
                ? new QwenAiProvider()
                : new LogAiProvider(),
            'deepseek' => $this->filled(config('services.deepseek.key'))
                ? new DeepSeekAiProvider()
                : new LogAiProvider(),
            default => new LogAiProvider(),
        };
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
