<?php

namespace App\Modules\Notification\Providers\Email;

use App\Contracts\EmailProviderInterface;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * SMTP email provider — uses Laravel's built-in Mail facade (the configured
 * 'smtp' mailer). The fallback for any SMTP server when a tenant does not use a
 * hosted API provider.
 *
 * Mail::html() sends a raw-HTML message without needing a Mailable class — the
 * body is the rendered HTML from the notification template.
 *
 * Never throws (EmailProviderInterface contract): any send error is logged and
 * returns false.
 */
class SmtpEmailProvider implements EmailProviderInterface
{
    public function send(
        string $to,
        string $subject,
        string $body,
        int $tenantId,
        ?string $replyTo = null,
    ): bool {
        try {
            Mail::mailer('smtp')->html($body, function ($message) use ($to, $subject, $replyTo): void {
                $message->to($to)->subject($subject);

                if ($replyTo !== null) {
                    $message->replyTo($replyTo);
                }
            });

            return true;
        } catch (Throwable $e) {
            Log::error('SmtpEmailProvider: send failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
