<?php

namespace App\Modules\Fleet\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Events\VehicleStatusChanged;
use App\Modules\Fleet\Models\Vehicle;
use InvalidArgumentException;

/**
 * Changes a vehicle's status — the ONE and ONLY sanctioned way to do so.
 *
 * Anywhere a vehicle's status must change (rental lifecycle, return inspection,
 * workshop, manual admin action), call this action. NEVER mutate the status
 * directly (no $vehicle->update(['status' => ...]) and no $vehicle->status =
 * ... ; $vehicle->save()) — doing so bypasses validation and, critically,
 * skips the VehicleStatusChanged event that downstream listeners depend on.
 */
class ChangeVehicleStatusAction extends BaseAction
{
    /**
     * @throws InvalidArgumentException when $newStatus is not one of the six
     *                                   valid Vehicle::STATUSES.
     */
    public function execute(Vehicle $vehicle, string $newStatus): Vehicle
    {
        if (! in_array($newStatus, Vehicle::STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid vehicle status [{$newStatus}]. Allowed: "
                .implode(', ', Vehicle::STATUSES).'.'
            );
        }

        $oldStatus = $vehicle->status;

        // No-op transition: nothing changed, so don't write or emit an event.
        if ($oldStatus === $newStatus) {
            return $vehicle;
        }

        $vehicle->status = $newStatus;
        $vehicle->save();

        VehicleStatusChanged::dispatch($vehicle, $oldStatus, $newStatus);

        return $vehicle;
    }
}
