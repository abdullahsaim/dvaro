<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Actions\ChangeVehicleStatusAction;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Workshop\Events\MaintenanceCompleted;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Changes a service log's status — the ONE sanctioned path.
 *
 * Completing a log is special: it stamps completed_at, returns the vehicle to
 * 'available' (via ChangeVehicleStatusAction — never directly) and fires
 * MaintenanceCompleted. On every transition total_cost is refreshed from
 * labour_cost + the current sum of parts, so it can never drift.
 */
class ChangeServiceLogStatusAction extends BaseAction
{
    public function __construct(
        private readonly ChangeVehicleStatusAction $changeVehicleStatus,
    ) {}

    /**
     * @throws InvalidArgumentException when $newStatus is not a valid status.
     */
    public function execute(ServiceLog $log, string $newStatus): ServiceLog
    {
        if (! in_array($newStatus, ServiceLog::STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid service log status [{$newStatus}]. Allowed: "
                .implode(', ', ServiceLog::STATUSES).'.'
            );
        }

        return DB::transaction(function () use ($log, $newStatus) {
            $wasComplete = $log->isComplete();

            $log->status = $newStatus;

            // Keep the cached total authoritative on every transition.
            $log->total_cost = (int) $log->labour_cost + $log->totalPartsCost();

            if ($newStatus === ServiceLog::STATUS_COMPLETED) {
                $log->completed_at = now();
            }

            $log->save();

            // Return the vehicle to service only on the transition INTO completed
            // (not on a re-save of an already-complete log).
            if ($newStatus === ServiceLog::STATUS_COMPLETED && ! $wasComplete) {
                $vehicle = Vehicle::findOrFail($log->vehicle_id);
                $this->changeVehicleStatus->execute($vehicle, Vehicle::STATUS_AVAILABLE);

                MaintenanceCompleted::dispatch($log);
            }

            return $log;
        });
    }
}
