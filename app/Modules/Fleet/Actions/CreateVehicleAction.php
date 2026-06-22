<?php

namespace App\Modules\Fleet\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\DTOs\CreateVehicleDTO;
use App\Modules\Fleet\Events\VehicleCreated;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Services\PlanEnforcementService;

/**
 * Adds a vehicle to the bound tenant's fleet.
 *
 * Plan limits are a HARD BLOCK: the max_vehicles limit is checked BEFORE the
 * insert. If the tenant is already at its limit, PlanEnforcementService throws
 * PlanLimitExceededException (403 + upgrade message) and nothing is created.
 */
class CreateVehicleAction extends BaseAction
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    public function execute(CreateVehicleDTO $dto): Vehicle
    {
        // Vehicle::count() is tenant-scoped via TenantScope, so this counts only
        // the current tenant's vehicles. Soft-deleted rows are excluded, which
        // is correct — an archived vehicle does not occupy a plan slot.
        $this->planEnforcement->check('max_vehicles', Vehicle::count());

        $vehicle = Vehicle::create($dto->toAttributes());

        VehicleCreated::dispatch($vehicle);

        return $vehicle;
    }
}
