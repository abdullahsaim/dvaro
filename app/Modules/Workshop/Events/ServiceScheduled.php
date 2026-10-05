<?php

namespace App\Modules\Workshop\Events;

use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a future service appointment is booked (the vehicle stays in
 * service until work actually starts), exclusively from ScheduleServiceAction.
 * Distinct from MaintenanceStarted, which marks the vehicle actually entering
 * the workshop.
 */
class ServiceScheduled
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ServiceLog $serviceLog,
    ) {}
}
