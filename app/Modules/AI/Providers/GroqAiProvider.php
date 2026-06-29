<?php

namespace App\Modules\AI\Providers;

/**
 * Groq — OpenAI-compatible chat completions. Fast inference, free tier available.
 * Model: llama-3.3-70b-versatile. Auth: Bearer GROQ_API_KEY.
 */
class GroqAiProvider extends AbstractHttpAiProvider
{
    public function name(): string
    {
        return 'groq';
    }

    public function maxTokens(): int
    {
        return 8192;
    }

    protected function endpoint(): string
    {
        return 'https://api.groq.com/openai/v1/chat/completions';
    }

    protected function model(): string
    {
        return 'llama-3.3-70b-versatile';
    }

    protected function apiKey(): ?string
    {
        return config('services.groq.key');
    }
}
