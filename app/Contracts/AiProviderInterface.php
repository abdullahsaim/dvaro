<?php

namespace App\Contracts;

/**
 * Contract for AI providers (Groq, Qwen, DeepSeek).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant and
 * dispatch through this interface. Tenant-restricted; all calls must be queued.
 */
interface AiProviderInterface
{
    public function query(string $prompt): string;
}
