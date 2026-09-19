<?php

namespace App\Modules\Fleet\Events;

use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an odometer reading is recorded (any source), exclusively from
 * RecordOdometerReadingAction. No listeners yet — km-based reminders are swept
 * daily by FleetReminderService.
 */
class OdometerReadingRecorded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly OdometerReading $reading,
    ) {}
}
