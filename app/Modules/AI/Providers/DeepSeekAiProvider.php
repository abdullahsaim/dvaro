<?php

namespace App\Modules\AI\Providers;

/**
 * DeepSeek — OpenAI-compatible chat completions.
 * Model: deepseek-chat. Auth: Bearer DEEPSEEK_API_KEY.
 */
class DeepSeekAiProvider extends AbstractHttpAiProvider
{
    public function name(): string
    {
        return 'deepseek';
    }

    public function maxTokens(): int
    {
        return 4096;
    }

    protected function endpoint(): string
    {
        return 'https://api.deepseek.com/chat/completions';
    }

    protected function model(): string
    {
        return 'deepseek-chat';
    }

    protected function apiKey(): ?string
    {
        return config('services.deepseek.key');
    }
}
