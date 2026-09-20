<?php

namespace App\Jobs;

use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Templates\StaffInvitationTemplate;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Scopes\TenantScope;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Emails a staff invitation link. Queued on 'notifications' (CLAUDE.md:
 * messaging is never synchronous) and sent through the tenant's own email
 * provider, so it is logged like every other message.
 *
 * Runs with NO bound tenant (queue worker): the invitation is loaded
 * scope-free with an explicit tenant_id.
 */
class SendStaffInvitationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const EVENT_TYPE = 'staff.invitation';

    public int $tries = 1;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $invitationId,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(NotificationService $notifications): void
    {
        $tenant = Tenant::find($this->tenantId);

        $invitation = TenantUserInvitation::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $this->tenantId)
            ->find($this->invitationId);

        if ($tenant === null || $invitation === null || ! $invitation->isPending()) {
            return;
        }

        $url = route('tenant.staff.invite.show', [
            'tenant_slug' => $tenant->slug,
            'token' => $invitation->token,
        ]);

        $content = (new StaffInvitationTemplate)->build(
            $tenant->name,
            $invitation->name,
            $url,
            $invitation->expires_at,
        );

        $notifications->sendEmail(
            $tenant,
            $invitation->email,
            $content->subject,
            $content->emailBody,
            self::EVENT_TYPE,
            $invitation->id,
            'tenant_user_invitation',
        );
    }
}
