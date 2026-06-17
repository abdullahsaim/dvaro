<?php

namespace App\Modules\Fleet\Events;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired whenever a vehicle's status changes, exclusively from
 * ChangeVehicleStatusAction. Carries the vehicle and both the previous and
 * new status so listeners can react to specific transitions.
 *
 * No listeners exist yet — they live in later sessions (notifications,
 * fleet history / audit log, utilisation tracking).
 */
class VehicleStatusChanged
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}
}
