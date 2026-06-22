<?php

namespace App\Modules\Notification\Providers\WhatsApp;

use App\Contracts\WhatsAppProviderInterface;
use Illuminate\Support\Facades\Log;

/**
 * Default WhatsApp provider when ClickSend WhatsApp credentials are not
 * configured. Writes to the Laravel log instead of sending, always returns true.
 */
class LogWhatsAppProvider implements WhatsAppProviderInterface
{
    public function send(string $to, string $message, int $tenantId): bool
    {
        Log::info('[LogWhatsAppProvider] WhatsApp (not really sent)', [
            'tenant_id' => $tenantId,
            'to' => $to,
            'message' => $message,
        ]);

        return true;
    }
}
