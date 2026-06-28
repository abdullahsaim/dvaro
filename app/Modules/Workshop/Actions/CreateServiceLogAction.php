<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Actions\ChangeVehicleStatusAction;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Workshop\DTOs\CreateServiceLogDTO;
use App\Modules\Workshop\Events\MaintenanceStarted;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Support\Facades\DB;

/**
 * Opens a new service log against a vehicle and puts that vehicle into the
 * workshop.
 *
 * The whole thing is one transaction: the log row, the vehicle status flip
 * (maintenance) and the MaintenanceStarted event are all-or-nothing. Vehicle
 * status mutates ONLY through ChangeVehicleStatusAction — never directly here.
 */
class CreateServiceLogAction extends BaseAction
{
    public function __construct(
        private readonly ChangeVehicleStatusAction $changeVehicleStatus,
    ) {}

    public function execute(CreateServiceLogDTO $dto, Mechanic $mechanic): ServiceLog
    {
        return DB::transaction(function () use ($dto, $mechanic) {
            // Resolved through TenantScope — a cross-tenant vehicle_id is never
            // found, so a mechanic can only ever log against their own fleet.
            $vehicle = Vehicle::findOrFail($dto->vehicle_id);

            $log = ServiceLog::create([
                'vehicle_id' => $vehicle->id,
                'mechanic_id' => $mechanic->id,
                'status' => ServiceLog::STATUS_PENDING,
                'title' => $dto->title,
                'description' => $dto->description,
                'odometer_reading' => $dto->odometer_reading,
                'labour_cost' => $dto->labour_cost,
                'total_cost' => $dto->labour_cost, // no parts yet
                'started_at' => now(),
            ]);

            // The sanctioned path — validates + fires VehicleStatusChanged.
            $this->changeVehicleStatus->execute($vehicle, Vehicle::STATUS_MAINTENANCE);

            MaintenanceStarted::dispatch($log);

            return $log;
        });
    }
}
