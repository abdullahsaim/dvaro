<?php

namespace App\Modules\Fleet\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\DTOs\CreateVehicleDTO;
use App\Modules\Fleet\Events\VehicleCreated;
use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use Illuminate\Support\Facades\DB;

/**
 * Adds a vehicle to the bound tenant's fleet.
 *
 * Plan limits are a HARD BLOCK: the max_vehicles limit is checked BEFORE the
 * insert. If the tenant is already at its limit, PlanEnforcementService throws
 * PlanLimitExceededException (403 + upgrade message) and nothing is created.
 *
 * The service schedule is derived on create (applyServiceSchedule), and a
 * starting odometer is recorded as the vehicle's FIRST reading through
 * RecordOdometerReadingAction — never mass-assigned — all in one transaction.
 */
class CreateVehicleAction extends BaseAction
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
        private readonly RecordOdometerReadingAction $recordOdometer,
    ) {}

    public function execute(CreateVehicleDTO $dto, ?int $actorId = null): Vehicle
    {
        // Vehicle::count() is tenant-scoped via TenantScope, so this counts only
        // the current tenant's vehicles. Soft-deleted rows are excluded, which
        // is correct — an archived vehicle does not occupy a plan slot.
        $this->planEnforcement->check('max_vehicles', Vehicle::count());

        $vehicle = DB::transaction(function () use ($dto, $actorId) {
            $vehicle = new Vehicle($dto->toAttributes());
            $vehicle->applyServiceSchedule()->save();

            if ($dto->current_odometer !== null) {
                $this->recordOdometer->execute(
                    $vehicle,
                    $dto->current_odometer,
                    OdometerReading::SOURCE_MANUAL,
                    $actorId !== null ? OdometerReading::ACTOR_TENANT_USER : null,
                    $actorId,
                );
            }

            return $vehicle;
        });

        VehicleCreated::dispatch($vehicle);

        return $vehicle;
    }
}
