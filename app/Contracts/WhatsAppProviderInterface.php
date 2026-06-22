<?php

namespace App\Contracts;

/**
 * Contract for WhatsApp providers (ClickSend via Meta BSP, Log).
 *
 * WhatsApp is platform-wide ClickSend per CLAUDE.md (not tenant-selectable like
 * SMS/email). Resolve via NotificationProviderFactory::whatsapp() — never call
 * the SDK directly.
 *
 * CONTRACT: send() NEVER throws. It catches every error internally, logs it,
 * and returns false. Returns true only on a confirmed provider success.
 */
interface WhatsAppProviderInterface
{
    public function send(string $to, string $message, int $tenantId): bool;
}
