<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Workshop\DTOs\UpdateMechanicDTO;
use App\Modules\Workshop\Models\Mechanic;

/**
 * Updates a workshop login (Mechanic). Thin: the DTO already decides which
 * fields to write (blank pin/password are omitted so the stored hash survives).
 */
class UpdateMechanicAction extends BaseAction
{
    public function execute(Mechanic $mechanic, UpdateMechanicDTO $dto): Mechanic
    {
        $mechanic->update($dto->toAttributes());

        return $mechanic;
    }
}
