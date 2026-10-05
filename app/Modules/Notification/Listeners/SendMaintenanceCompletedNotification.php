<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Templates\MaintenanceCompletedTemplate;
use App\Modules\Workshop\Events\MaintenanceCompleted;

/**
 * Notifies the tenant admin when a workshop job finishes and the vehicle is
 * back in service. Queued.
 */
class SendMaintenanceCompletedNotification extends QueuedNotificationListener
{
    public function handle(MaintenanceCompleted $event): void
    {
        $log = $event->serviceLog;

        $tenant = $this->bindTenant((int) $log->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            $log->loadMissing(['vehicle', 'mechanic']);
            $content = (new MaintenanceCompletedTemplate)->build($log);
            $this->notifyAdmin($tenant, $content, 'workshop.maintenance_completed');
        } finally {
            $this->forgetTenant();
        }
    }
}
