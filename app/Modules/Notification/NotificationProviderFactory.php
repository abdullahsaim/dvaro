<?php

namespace App\Modules\Notification;

use App\Contracts\EmailProviderInterface;
use App\Contracts\SmsProviderInterface;
use App\Contracts\WhatsAppProviderInterface;
use App\Modules\Notification\Providers\Email\LogEmailProvider;
use App\Modules\Notification\Providers\Email\MailgunEmailProvider;
use App\Modules\Notification\Providers\Email\ResendEmailProvider;
use App\Modules\Notification\Providers\Email\SmtpEmailProvider;
use App\Modules\Notification\Providers\Sms\CellcastSmsProvider;
use App\Modules\Notification\Providers\Sms\ClickSendSmsProvider;
use App\Modules\Notification\Providers\Sms\LogSmsProvider;
use App\Modules\Notification\Providers\WhatsApp\ClickSendWhatsAppProvider;
use App\Modules\Notification\Providers\WhatsApp\LogWhatsAppProvider;
use App\Modules\SaasCore\Models\Tenant;

/**
 * Resolves the concrete notification provider for a tenant per the provider-
 * switching pattern (CLAUDE.md): callers never new-up a provider directly.
 *
 * Selection is driven by tenant->settings ('sms_provider', 'email_provider').
 * CRITICAL fallback rule: a provider is only returned when its credentials are
 * actually configured — otherwise we fall back to the Log* provider. This is
 * what realises "Log by default; real providers activate the moment credentials
 * land in .env" with zero code changes. WhatsApp is platform-wide ClickSend
 * (not tenant-selectable) per CLAUDE.md, with the same Log fallback.
 */
class NotificationProviderFactory
{
    public function sms(Tenant $tenant): SmsProviderInterface
    {
        $choice = (string) ($tenant->settings['sms_provider'] ?? 'log');

        return match ($choice) {
            'clicksend' => $this->clickSendConfigured()
                ? new ClickSendSmsProvider()
                : new LogSmsProvider(),
            'cellcast' => $this->filled(config('services.cellcast.api_key'))
                ? new CellcastSmsProvider()
                : new LogSmsProvider(),
            default => new LogSmsProvider(),
        };
    }

    public function email(Tenant $tenant): EmailProviderInterface
    {
        $choice = (string) ($tenant->settings['email_provider'] ?? 'log');

        return match ($choice) {
            'mailgun' => $this->filled(config('services.mailgun.domain'))
                && $this->filled(config('services.mailgun.secret'))
                    ? new MailgunEmailProvider()
                    : new LogEmailProvider(),
            'resend' => $this->filled(config('services.resend.key'))
                ? new ResendEmailProvider()
                : new LogEmailProvider(),
            // SMTP relies on the framework mail config, not a service key here;
            // trust the tenant's explicit choice and let Laravel's mailer resolve.
            'smtp' => new SmtpEmailProvider(),
            default => new LogEmailProvider(),
        };
    }

    public function whatsapp(Tenant $tenant): WhatsAppProviderInterface
    {
        // Always ClickSend platform-wide (CLAUDE.md) — never tenant-selectable.
        return $this->clickSendConfigured() && $this->filled(config('services.clicksend.whatsapp_number'))
            ? new ClickSendWhatsAppProvider()
            : new LogWhatsAppProvider();
    }

    private function clickSendConfigured(): bool
    {
        return $this->filled(config('services.clicksend.username'))
            && $this->filled(config('services.clicksend.api_key'));
    }

    private function filled(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }
}
