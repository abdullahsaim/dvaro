<?php

namespace App\Modules\Notification\Providers\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ClickSend SMS provider — POSTs to the ClickSend REST API (v3).
 *
 * Auth: HTTP Basic with CLICKSEND_USERNAME + CLICKSEND_API_KEY.
 *
 * Never throws (SmsProviderInterface contract): any transport error, non-2xx
 * response, or per-message failure is logged and returns false.
 */
class ClickSendSmsProvider implements SmsProviderInterface
{
    private const ENDPOINT = 'https://rest.clicksend.com/v3/sms/send';

    public function send(string $to, string $message, int $tenantId): bool
    {
        try {
            $username = (string) config('services.clicksend.username');
            $apiKey = (string) config('services.clicksend.api_key');

            $response = Http::withBasicAuth($username, $apiKey)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'messages' => [[
                        'source' => 'php',
                        'to' => $to,
                        'body' => $message,
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('ClickSendSmsProvider: non-success response', [
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            // ClickSend wraps results: response_code SUCCESS at the top level and
            // a per-message status under data.messages[].status.
            $data = $response->json();
            $messageStatus = data_get($data, 'data.messages.0.status');

            if (data_get($data, 'response_code') !== 'SUCCESS' || $messageStatus !== 'SUCCESS') {
                Log::warning('ClickSendSmsProvider: message not accepted', [
                    'tenant_id' => $tenantId,
                    'response' => $data,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('ClickSendSmsProvider: send failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
