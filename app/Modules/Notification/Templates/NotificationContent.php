<?php

namespace App\Modules\Notification\Templates;

/**
 * The rendered output of a notification template: one subject plus a body per
 * channel. Email bodies are HTML; SMS/WhatsApp bodies are plain text.
 *
 * WhatsApp falls back to the SMS body when not given a distinct one — the two
 * are usually the same short message, and v1 has no rich WhatsApp formatting.
 *
 * Immutable + free of any DB or I/O, so templates are trivially unit-testable.
 */
final class NotificationContent
{
    public function __construct(
        public readonly string $subject,
        public readonly string $emailBody,
        public readonly string $smsBody,
        private readonly ?string $whatsappBody = null,
    ) {}

    public function whatsappBody(): string
    {
        return $this->whatsappBody ?? $this->smsBody;
    }
}
