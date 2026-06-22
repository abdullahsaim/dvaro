<?php

namespace App\Modules\Notification\Services;

use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\NotificationProviderFactory;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Throwable;

/**
 * The single sanctioned entry point for dispatching a notification on any
 * channel. Resolves the tenant's provider (NotificationProviderFactory), sends,
 * and writes EXACTLY ONE NotificationLog row per attempt — success or failure.
 *
 * NEVER throws: a failed send (or even a thrown provider, which the contract
 * forbids but we still guard) becomes a failed log row and a false return. A
 * notification must never break the business action that triggered it.
 *
 * Context-independent: tenant_id on the log is set explicitly from the passed
 * Tenant, so writes are correct even with no bound current_tenant (queue/console
 * context) — though listeners bind the tenant anyway for relation traversal.
 */
class NotificationService extends BaseService
{
    public function __construct(
        private readonly NotificationProviderFactory $factory,
    ) {}

    public function sendSms(
        Tenant $tenant,
        string $to,
        string $message,
        string $eventType,
        ?int $notifiableId = null,
        ?string $notifiableType = null,
    ): bool {
        $provider = $this->factory->sms($tenant);

        return $this->dispatch(
            tenant: $tenant,
            channel: NotificationLog::CHANNEL_SMS,
            provider: $this->providerName($provider),
            eventType: $eventType,
            recipient: $to,
            subject: null,
            body: $message,
            notifiableId: $notifiableId,
            notifiableType: $notifiableType,
            send: fn (): bool => $provider->send($to, $message, $tenant->id),
        );
    }

    public function sendEmail(
        Tenant $tenant,
        string $to,
        string $subject,
        string $body,
        string $eventType,
        ?int $notifiableId = null,
        ?string $notifiableType = null,
        ?string $replyTo = null,
    ): bool {
        $provider = $this->factory->email($tenant);

        return $this->dispatch(
            tenant: $tenant,
            channel: NotificationLog::CHANNEL_EMAIL,
            provider: $this->providerName($provider),
            eventType: $eventType,
            recipient: $to,
            subject: $subject,
            body: $body,
            notifiableId: $notifiableId,
            notifiableType: $notifiableType,
            send: fn (): bool => $provider->send($to, $subject, $body, $tenant->id, $replyTo),
        );
    }

    public function sendWhatsApp(
        Tenant $tenant,
        string $to,
        string $message,
        string $eventType,
        ?int $notifiableId = null,
        ?string $notifiableType = null,
    ): bool {
        $provider = $this->factory->whatsapp($tenant);

        return $this->dispatch(
            tenant: $tenant,
            channel: NotificationLog::CHANNEL_WHATSAPP,
            provider: $this->providerName($provider),
            eventType: $eventType,
            recipient: $to,
            subject: null,
            body: $message,
            notifiableId: $notifiableId,
            notifiableType: $notifiableType,
            send: fn (): bool => $provider->send($to, $message, $tenant->id),
        );
    }

    /**
     * Run the send closure, then persist the outcome. The closure is the only
     * thing that touches a provider; everything else here is logging. Any
     * unexpected throw is swallowed into a failed row (defence in depth — the
     * provider contract already forbids throwing).
     */
    private function dispatch(
        Tenant $tenant,
        string $channel,
        string $provider,
        string $eventType,
        string $recipient,
        ?string $subject,
        string $body,
        ?int $notifiableId,
        ?string $notifiableType,
        callable $send,
    ): bool {
        $error = null;

        try {
            $ok = (bool) $send();
        } catch (Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
        }

        NotificationLog::create([
            // Set explicitly so the write is correct with no bound tenant.
            'tenant_id' => $tenant->id,
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'channel' => $channel,
            'event_type' => $eventType,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $body,
            'status' => $ok ? NotificationLog::STATUS_SENT : NotificationLog::STATUS_FAILED,
            'provider' => $provider,
            'provider_message_id' => null,
            'error_message' => $error,
            'sent_at' => $ok ? now() : null,
        ]);

        return $ok;
    }

    private function providerName(object $provider): string
    {
        // 'ClickSendSmsProvider' → 'clicksend'-ish slug for the log column.
        $base = class_basename($provider);
        $base = preg_replace('/(SmsProvider|EmailProvider|WhatsAppProvider)$/', '', $base) ?? $base;

        return strtolower($base);
    }
}
