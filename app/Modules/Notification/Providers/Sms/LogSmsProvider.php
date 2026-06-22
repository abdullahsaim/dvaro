<?php

namespace App\Modules\Notification\Providers\Sms;

use App\Contracts\SmsProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Default SMS provider when no real credentials are configured. Writes the
 * message to the Laravel log instead of sending, and always returns true (a log
 * write never fails). This is what makes the whole notification pipeline work
 * end-to-end with no API keys — real providers activate by setting
 * settings.sms_provider + dropping credentials into .env.
 */
class LogSmsProvider implements SmsProviderInterface
{
    public function send(string $to, string $message, int $tenantId): bool
    {
        Log::info('[LogSmsProvider] SMS (not really sent)', [
            'tenant_id' => $tenantId,
            'to' => $to,
            'message' => $message,
        ]);

        return true;
    }
}
