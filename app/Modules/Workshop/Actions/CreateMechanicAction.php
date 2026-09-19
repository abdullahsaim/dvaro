<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use App\Modules\Workshop\DTOs\CreateMechanicDTO;
use App\Modules\Workshop\Models\Mechanic;

/**
 * Creates a workshop login (Mechanic) for the bound tenant.
 *
 * HasTenant fills tenant_id from the bound tenant; the model's 'hashed' casts
 * hash pin/password on save.
 *
 * Plan limit max_mechanics is a HARD BLOCK checked before insert
 * (PlanLimitExceededException). The count is tenant-scoped and excludes
 * soft-deleted mechanics but INCLUDES deactivated ones — same rule as staff —
 * so deactivate/reactivate can't be used to exceed the plan.
 */
class CreateMechanicAction extends BaseAction
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    public function execute(CreateMechanicDTO $dto): Mechanic
    {
        $this->planEnforcement->check('max_mechanics', Mechanic::count());

        return Mechanic::create($dto->toAttributes());
    }
}
