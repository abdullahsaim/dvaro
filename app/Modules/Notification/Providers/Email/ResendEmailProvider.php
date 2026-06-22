<?php

namespace App\Modules\Notification\Providers\Email;

use App\Contracts\EmailProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Resend email provider — POSTs to the Resend Emails API.
 *
 * Auth: Bearer RESEND_API_KEY. The from-address is shared with Mailgun config
 * (mailgun.from) so a tenant can swap providers without re-stating the sender.
 *
 * Never throws (EmailProviderInterface contract): any transport error or non-2xx
 * response is logged and returns false.
 */
class ResendEmailProvider implements EmailProviderInterface
{
    private const ENDPOINT = 'https://api.resend.com/emails';

    public function send(
        string $to,
        string $subject,
        string $body,
        int $tenantId,
        ?string $replyTo = null,
    ): bool {
        try {
            $apiKey = (string) config('services.resend.key');
            $from = (string) config('services.mailgun.from');

            $payload = [
                'from' => $from,
                'to' => [$to],
                'subject' => $subject,
                'html' => $body,
            ];

            if ($replyTo !== null) {
                $payload['reply_to'] = $replyTo;
            }

            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, $payload);

            if (! $response->successful()) {
                Log::warning('ResendEmailProvider: non-success response', [
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('ResendEmailProvider: send failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
