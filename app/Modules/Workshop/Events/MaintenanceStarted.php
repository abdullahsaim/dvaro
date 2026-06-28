<?php

namespace App\Modules\Workshop\Events;

use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a service log is created (a vehicle enters the workshop),
 * exclusively from CreateServiceLogAction.
 *
 * No listeners exist yet (notifications / workshop analytics are later sessions).
 */
class MaintenanceStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ServiceLog $serviceLog,
    ) {}
}
