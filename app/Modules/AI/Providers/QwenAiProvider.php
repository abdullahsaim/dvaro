<?php

namespace App\Modules\AI\Providers;

/**
 * Qwen (Alibaba DashScope) via the OpenAI-compatible endpoint.
 * Model: qwen-turbo. Auth: Bearer QWEN_API_KEY.
 */
class QwenAiProvider extends AbstractHttpAiProvider
{
    public function name(): string
    {
        return 'qwen';
    }

    public function maxTokens(): int
    {
        return 6144;
    }

    protected function endpoint(): string
    {
        return 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions';
    }

    protected function model(): string
    {
        return 'qwen-turbo';
    }

    protected function apiKey(): ?string
    {
        return config('services.qwen.key');
    }
}
