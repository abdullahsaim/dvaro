<?php

namespace App\Modules\Workshop\Events;

use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a service log is completed (the vehicle returns to 'available'),
 * exclusively from ChangeServiceLogStatusAction.
 *
 * No listeners exist yet (notifications / workshop analytics are later sessions).
 */
class MaintenanceCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ServiceLog $serviceLog,
    ) {}
}
