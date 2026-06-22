<?php

namespace App\Contracts;

/**
 * Contract for email providers (Mailgun, Resend, SMTP, Log).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant via
 * NotificationProviderFactory and dispatch through this interface. Sends are
 * driven from queued notification listeners.
 *
 * The body is rendered HTML (templates produce it). $replyTo is optional.
 *
 * CONTRACT: send() NEVER throws. It catches every error internally, logs it,
 * and returns false. Returns true only on a confirmed provider success.
 */
interface EmailProviderInterface
{
    public function send(
        string $to,
        string $subject,
        string $body,
        int $tenantId,
        ?string $replyTo = null,
    ): bool;
}
