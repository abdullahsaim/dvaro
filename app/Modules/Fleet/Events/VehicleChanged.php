<?php

namespace App\Modules\Fleet\Events;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Fleet\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a rental's vehicle is swapped mid-agreement (CLAUDE.md required
 * event), exclusively from VehicleChangeService::execute(). Carries the
 * agreement being changed, both vehicles, and the effective change date.
 *
 * No listeners exist yet — notifications (customer "vehicle changed" email/SMS),
 * fleet utilisation updates, and audit logging attach in later sessions.
 */
class VehicleChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Agreement $agreement,
        public readonly Vehicle $oldVehicle,
        public readonly Vehicle $newVehicle,
        public readonly Carbon $changeDate,
    ) {}
}
