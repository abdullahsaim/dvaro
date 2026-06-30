<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Workshop\DTOs\CreateMechanicDTO;
use App\Modules\Workshop\Models\Mechanic;

/**
 * Creates a workshop login (Mechanic) for the bound tenant.
 *
 * HasTenant fills tenant_id from the bound tenant; the model's 'hashed' casts
 * hash pin/password on save. No plan-limit enforcement this session (mechanics
 * are not yet a metered plan resource).
 */
class CreateMechanicAction extends BaseAction
{
    public function execute(CreateMechanicDTO $dto): Mechanic
    {
        return Mechanic::create($dto->toAttributes());
    }
}
