<?php

namespace App\Modules\Fleet\Events;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when a vehicle is added to a tenant's fleet, exclusively from
 * CreateVehicleAction. Carries the newly created vehicle.
 *
 * No listeners exist yet — they live in later sessions (fleet history / audit
 * log, utilisation tracking, notifications).
 */
class VehicleCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vehicle $vehicle,
    ) {}
}
