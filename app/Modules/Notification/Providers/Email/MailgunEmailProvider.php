<?php

namespace App\Modules\Notification\Providers\Email;

use App\Contracts\EmailProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Mailgun email provider — POSTs to the Mailgun Messages API.
 *
 * Auth: HTTP Basic with username 'api' and the MAILGUN_SECRET. The sending
 * domain and from-address come from config/services.php (mailgun.*).
 *
 * Never throws (EmailProviderInterface contract): any transport error or non-2xx
 * response is logged and returns false.
 */
class MailgunEmailProvider implements EmailProviderInterface
{
    public function send(
        string $to,
        string $subject,
        string $body,
        int $tenantId,
        ?string $replyTo = null,
        ?array $attachments = null,
    ): bool {
        try {
            $domain = (string) config('services.mailgun.domain');
            $secret = (string) config('services.mailgun.secret');
            $host = (string) config('services.mailgun.endpoint', 'api.mailgun.net');
            $from = (string) config('services.mailgun.from');

            $payload = [
                'from' => $from,
                'to' => $to,
                'subject' => $subject,
                'html' => $body,
            ];

            if ($replyTo !== null) {
                $payload['h:Reply-To'] = $replyTo;
            }

            $request = Http::withBasicAuth('api', $secret);

            // An attachment forces multipart (Mailgun has no attach-via-form
            // field) — asForm() is only used when there is nothing to attach.
            if ($attachments !== null && $attachments !== []) {
                foreach ($attachments as $attachment) {
                    $request = $request->attach('attachment', $attachment['content'], $attachment['filename']);
                }
            } else {
                $request = $request->asForm();
            }

            $response = $request->post("https://{$host}/v3/{$domain}/messages", $payload);

            if (! $response->successful()) {
                Log::warning('MailgunEmailProvider: non-success response', [
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('MailgunEmailProvider: send failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
