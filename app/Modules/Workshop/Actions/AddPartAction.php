<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Workshop\Models\PartUsed;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Support\Facades\DB;

/**
 * Adds a spare part to a service log and refreshes the log's cached total.
 *
 * PartUsed.total_cost (quantity × unit_cost) is computed by the model's saving
 * hook; here we then roll that into the log's total_cost = labour + all parts.
 * One transaction so a part is never recorded without the total catching up.
 */
class AddPartAction extends BaseAction
{
    public function execute(ServiceLog $log, string $name, int $quantity, int $unitCost): PartUsed
    {
        return DB::transaction(function () use ($log, $name, $quantity, $unitCost) {
            $part = $log->parts()->create([
                'name' => $name,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                // total_cost is set authoritatively by PartUsed's saving hook.
            ]);

            $log->total_cost = (int) $log->labour_cost + $log->totalPartsCost();
            $log->save();

            return $part;
        });
    }
}
