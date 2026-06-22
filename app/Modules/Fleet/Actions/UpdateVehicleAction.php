<?php

namespace App\Modules\Fleet\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\DTOs\UpdateVehicleDTO;
use App\Modules\Fleet\Models\Vehicle;

/**
 * Updates a vehicle's editable attributes.
 *
 * Status is intentionally NOT updatable here — UpdateVehicleDTO carries no
 * status field, so this can never bypass ChangeVehicleStatusAction (the one
 * sanctioned path for status transitions and the VehicleStatusChanged event).
 */
class UpdateVehicleAction extends BaseAction
{
    public function execute(Vehicle $vehicle, UpdateVehicleDTO $dto): Vehicle
    {
        $vehicle->update($dto->toAttributes());

        return $vehicle;
    }
}
