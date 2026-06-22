<?php

namespace App\Modules\Notification\Providers\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Cellcast SMS provider — POSTs to the Cellcast REST API.
 *
 * Auth: the CELLCAST_API_KEY is sent in the APPKEY header (Cellcast's scheme).
 *
 * Never throws (SmsProviderInterface contract): any transport error, non-2xx
 * response, or per-message failure is logged and returns false.
 */
class CellcastSmsProvider implements SmsProviderInterface
{
    private const ENDPOINT = 'https://cellcast.com.au/api/v3/send-sms';

    public function send(string $to, string $message, int $tenantId): bool
    {
        try {
            $apiKey = (string) config('services.cellcast.api_key');

            $response = Http::withHeaders([
                'APPKEY' => $apiKey,
            ])
                ->acceptJson()
                ->asJson()
                ->post(self::ENDPOINT, [
                    'sms_text' => $message,
                    'numbers' => [$to],
                ]);

            if (! $response->successful()) {
                Log::warning('CellcastSmsProvider: non-success response', [
                    'tenant_id' => $tenantId,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return false;
            }

            // Cellcast returns meta.status == 'SUCCESS' on accepted messages.
            $data = $response->json();

            if (data_get($data, 'meta.status') !== 'SUCCESS') {
                Log::warning('CellcastSmsProvider: message not accepted', [
                    'tenant_id' => $tenantId,
                    'response' => $data,
                ]);

                return false;
            }

            return true;
        } catch (Throwable $e) {
            Log::error('CellcastSmsProvider: send failed', [
                'tenant_id' => $tenantId,
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
