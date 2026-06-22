<?php

namespace App\Contracts;

/**
 * Contract for SMS providers (ClickSend, Cellcast, Log).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant via
 * NotificationProviderFactory and dispatch through this interface. Sends are
 * driven from queued notification listeners.
 *
 * CONTRACT: send() NEVER throws. It catches every error internally, logs it,
 * and returns false. Returns true only on a confirmed provider success. The
 * boolean is what NotificationService records as sent/failed.
 */
interface SmsProviderInterface
{
    public function send(string $to, string $message, int $tenantId): bool;
}
