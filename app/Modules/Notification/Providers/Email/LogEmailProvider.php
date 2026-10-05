<?php

namespace App\Modules\Notification\Providers\Email;

use App\Contracts\EmailProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Default email provider when no real credentials are configured. Writes the
 * message to the Laravel log instead of sending, and always returns true. Lets
 * the notification pipeline run end-to-end with no API keys — real providers
 * activate via settings.email_provider + .env credentials.
 */
class LogEmailProvider implements EmailProviderInterface
{
    public function send(
        string $to,
        string $subject,
        string $body,
        int $tenantId,
        ?string $replyTo = null,
        ?array $attachments = null,
    ): bool {
        Log::info('[LogEmailProvider] Email (not really sent)', [
            'tenant_id' => $tenantId,
            'to' => $to,
            'subject' => $subject,
            'reply_to' => $replyTo,
            'body' => $body,
            // Filenames only — never the raw bytes, to keep the log readable.
            'attachments' => array_map(fn (array $a) => $a['filename'], $attachments ?? []),
        ]);

        return true;
    }
}
