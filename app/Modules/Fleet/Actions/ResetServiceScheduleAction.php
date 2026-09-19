<?php

namespace App\Modules\Fleet\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Models\Vehicle;
use Carbon\CarbonInterface;

/**
 * Marks a scheduled service as done on the vehicle and re-derives the next
 * service (date + km, whichever comes first) from its intervals.
 *
 * Called when a service log flagged is_scheduled_service is completed
 * (ChangeServiceLogStatusAction). A null odometer falls back to the vehicle's
 * current reading so the km schedule still advances.
 */
class ResetServiceScheduleAction extends BaseAction
{
    public function execute(Vehicle $vehicle, CarbonInterface $servicedOn, ?int $odometer): Vehicle
    {
        $vehicle->last_service_date = $servicedOn->toDateString();
        $vehicle->last_service_odometer = $odometer ?? $vehicle->current_odometer;

        $vehicle->applyServiceSchedule()->save();

        return $vehicle;
    }
}
