<?php

namespace App\Jobs;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementSigningService;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Templates\AgreementSigningLinkTemplate;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends one agreement's review-and-sign link to its customer by email or
 * WhatsApp, through the tenant's own providers. Queued on 'notifications'
 * (CLAUDE.md: messaging is never synchronous). NotificationService logs the
 * attempt and never throws.
 *
 * Runs with NO bound tenant (queue worker) — binds it for the send, then
 * forgets it. The link is resolved at SEND time via AgreementSigningService
 * (not pre-built), so the agreement's current state is always reflected.
 */
class SendAgreementSigningLinkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    public const EVENT_TYPE = 'agreement.signing_link';

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $agreementId,
        public readonly string $channel,
        public readonly string $recipient,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications, AgreementSigningService $signing): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app()->instance('current_tenant', $tenant);

        try {
            $agreement = Agreement::find($this->agreementId);

            if ($agreement === null) {
                return;
            }

            $agreement->loadMissing('vehicle');

            $content = (new AgreementSigningLinkTemplate)->build(
                $tenant->name,
                $agreement,
                $signing->publicUrl($agreement, $tenant->slug),
            );

            if ($this->channel === self::CHANNEL_WHATSAPP) {
                $notifications->sendWhatsApp(
                    $tenant,
                    $this->recipient,
                    $content->whatsappBody(),
                    self::EVENT_TYPE,
                    (int) $agreement->id,
                    NotificationLog::TYPE_AGREEMENT,
                );
            } else {
                $notifications->sendEmail(
                    $tenant,
                    $this->recipient,
                    $content->subject,
                    $content->emailBody,
                    self::EVENT_TYPE,
                    (int) $agreement->id,
                    NotificationLog::TYPE_AGREEMENT,
                );
            }
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }
}
