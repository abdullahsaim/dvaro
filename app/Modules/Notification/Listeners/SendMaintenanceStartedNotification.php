<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Templates\MaintenanceStartedTemplate;
use App\Modules\Workshop\Events\MaintenanceStarted;

/**
 * Notifies the tenant admin when a vehicle enters the workshop. Queued.
 */
class SendMaintenanceStartedNotification extends QueuedNotificationListener
{
    public function handle(MaintenanceStarted $event): void
    {
        $log = $event->serviceLog;

        $tenant = $this->bindTenant((int) $log->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            $log->loadMissing(['vehicle', 'mechanic']);
            $content = (new MaintenanceStartedTemplate)->build($log);
            $this->notifyAdmin($tenant, $content, 'workshop.maintenance_started');
        } finally {
            $this->forgetTenant();
        }
    }
}
