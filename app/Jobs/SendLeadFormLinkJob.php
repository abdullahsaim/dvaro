<?php

namespace App\Jobs;

use App\Modules\CRM\Services\LeadFormService;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Templates\LeadFormLinkTemplate;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Sends a tenant's public lead-form link to one person by email or SMS through
 * the tenant's own providers. Queued on 'notifications' (CLAUDE.md: messaging
 * is never synchronous). NotificationService logs the attempt and never throws.
 *
 * Runs with NO bound tenant (queue worker) — binds it for the send, then
 * forgets it. The link is resolved at SEND time, so a regenerated token is
 * never mailed out stale.
 */
class SendLeadFormLinkJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    public const EVENT_TYPE = 'crm.lead_form_link';

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly string $channel,
        public readonly string $recipient,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications, LeadFormService $forms): void
    {
        $tenant = Tenant::find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        app()->instance('current_tenant', $tenant);

        try {
            $content = (new LeadFormLinkTemplate())->build($tenant->name, $forms->publicUrl($tenant));

            if ($this->channel === self::CHANNEL_SMS) {
                $notifications->sendSms($tenant, $this->recipient, $content->smsBody, self::EVENT_TYPE);
            } else {
                $notifications->sendEmail($tenant, $this->recipient, $content->subject, $content->emailBody, self::EVENT_TYPE);
            }
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }
}
