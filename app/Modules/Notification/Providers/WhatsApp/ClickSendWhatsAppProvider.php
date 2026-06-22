<?php

namespace App\Modules\Notification\Providers\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ClickSend WhatsApp provider — POSTs to the ClickSend WhatsApp API.
 *
 * Auth: the SAME HTTP Basic credentials as SMS (CLICKSEND_USERNAME +
 * CLICKSEND_API_KEY). Sends are billed against the registered WhatsApp number
 * (CLICKSEND_WHATSAPP_NUMBER).
 *
 * Never throws (WhatsAppProviderInterface contract): any transport error,
 * non-2xx response, or per-message failure is logged and returns false.
 */
class ClickSendWhatsAppProvider implements WhatsAppProviderInterface
{
    private const ENDPOINT = 'https://rest.clicksend.com/v3/whatsapp/send';

    public function send(string $to, string $message, int $tenantId): bool
    {
        try {
            $username = (string) config('services.clicksend.username');
            $apiKey = (string) config('services.clicksend.api_key');
            $from = (string) config('services.clicksend.whatsapp_number');

            $response = Http::withBasicAuth($username, $apiKey)
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'messages' => [[
                        'from' => $from,
                        'to' => $to,
                        'body' => $message,
                    ]],
                ]);

            if (! $response->successful()) {
                Log::warning('ClickSendWhatsAppProvider: non-success response', [
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            $data = $response->json();
            $messageStatus = data_get($data, 'data.messages.0.status');

            if (data_get($data, 'response_code') !== 'SUCCESS' || $messageStatus !== 'SUCCESS') {
                Log::warning('ClickSendWhatsAppProvider: message not accepted', [
                    'tenant_id' => $tenantId,
                    'response' => $data,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('ClickSendWhatsAppProvider: send failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
