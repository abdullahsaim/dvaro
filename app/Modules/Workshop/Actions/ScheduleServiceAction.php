<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Workshop\DTOs\ScheduleServiceDTO;
use App\Modules\Workshop\Events\ServiceScheduled;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Support\Facades\DB;

/**
 * Books a future workshop appointment — the ONE sanctioned path. Unlike
 * CreateServiceLogAction (an ad-hoc job starting right now), this does NOT
 * touch the vehicle's status: a booked vehicle stays available/rented until
 * the day work actually begins (ChangeServiceLogStatusAction flips it to
 * maintenance on the first transition out of "upcoming").
 */
class ScheduleServiceAction extends BaseAction
{
    public function execute(ScheduleServiceDTO $dto): ServiceLog
    {
        return DB::transaction(function () use ($dto) {
            // Resolved through TenantScope — cross-tenant ids are never found.
            Vehicle::findOrFail($dto->vehicle_id);
            Mechanic::findOrFail($dto->mechanic_id);

            $log = ServiceLog::create([
                'vehicle_id' => $dto->vehicle_id,
                'mechanic_id' => $dto->mechanic_id,
                'status' => ServiceLog::STATUS_PENDING,
                'title' => $dto->title,
                'description' => $dto->description,
                'scheduled_for' => $dto->scheduled_for,
                'is_scheduled_service' => true,
            ]);

            ServiceScheduled::dispatch($log);

            return $log;
        });
    }
}
