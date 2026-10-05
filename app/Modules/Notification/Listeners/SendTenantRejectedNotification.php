<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Services\NotificationMatrix;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\TenantApprovalNotifier;
use App\Modules\SuperAdmin\Events\TenantRejected;

/**
 * Tells the tenant's admin their registration was not approved. Queued like
 * every other outbound email (CLAUDE.md).
 */
class SendTenantRejectedNotification extends QueuedNotificationListener
{
    public function __construct(
        NotificationService $notifications,
        NotificationMatrix $matrix,
        private readonly TenantApprovalNotifier $approval,
    ) {
        parent::__construct($notifications, $matrix);
    }

    public function handle(TenantRejected $event): void
    {
        $tenant = $this->bindTenant((int) $event->tenant->id);
        if ($tenant === null) {
            return;
        }

        try {
            $this->approval->sendRejected($tenant);
        } finally {
            $this->forgetTenant();
        }
    }
}
