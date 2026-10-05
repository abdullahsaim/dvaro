<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Workshop\Models\ServiceLog;

/**
 * "A workshop job finished, the vehicle is back on the road" — sent to the
 * tenant admin. Pure: builds content from the service log.
 */
class MaintenanceCompletedTemplate
{
    use FormatsNotifications;

    public function build(ServiceLog $log): NotificationContent
    {
        $vehicle = $this->vehicleLabel($log);
        $cost = $this->money((int) $log->total_cost);

        $subject = "Workshop job completed — {$vehicle}";

        $emailBody = $this->emailHtml($subject, [
            "{$vehicle} is back in service.",
            "Job: {$log->title}",
            "Total cost: {$cost}",
            'The vehicle has been returned to Available.',
        ]);

        $sms = "{$vehicle} is back in service — {$log->title} complete ({$cost}).";

        return new NotificationContent($subject, $emailBody, $sms);
    }

    private function vehicleLabel(ServiceLog $log): string
    {
        $vehicle = $log->vehicle;

        if ($vehicle === null) {
            return 'a vehicle';
        }

        return trim("{$vehicle->make} {$vehicle->model} ({$vehicle->registration_number})");
    }
}
