<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Workshop\Models\ServiceLog;

/**
 * "A vehicle entered the workshop" — sent to the tenant admin, not a
 * customer. Pure: builds content from the service log.
 */
class MaintenanceStartedTemplate
{
    use FormatsNotifications;

    public function build(ServiceLog $log): NotificationContent
    {
        $vehicle = $this->vehicleLabel($log);
        $mechanic = $log->mechanic?->name ?? 'A mechanic';

        $subject = "Workshop job opened — {$vehicle}";

        $emailBody = $this->emailHtml($subject, [
            "{$mechanic} opened a workshop job for {$vehicle}.",
            "Job: {$log->title}",
            'Open the Workshop section to follow its progress.',
        ]);

        $sms = "{$vehicle} is now in the workshop ({$log->title}). Logged by {$mechanic}.";

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
