<?php

namespace App\Contracts;

/**
 * Contract for SMS providers (ClickSend, Cellcast).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant and
 * dispatch through this interface. All sends must be queued.
 */
interface SmsProviderInterface
{
    public function send(string $to, string $message): void;
}
