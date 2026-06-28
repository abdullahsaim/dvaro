<?php

namespace App\Modules\Workshop\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated new service-log data into CreateServiceLogAction.
 *
 * No tenant_id (HasTenant auto-populates it from the bound tenant on create).
 * mechanic_id is taken from the authenticated mechanic guard in the action, not
 * from the request — a mechanic can never log work under someone else's id.
 * labour_cost is in CENTS — matches the service_logs.labour_cost column.
 */
class CreateServiceLogDTO extends BaseDTO
{
    public function __construct(
        public readonly int $vehicle_id,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly ?int $odometer_reading = null,
        public readonly int $labour_cost = 0,
    ) {}

    public static function fromRequest(Request $request, int $vehicleId): self
    {
        return new self(
            vehicle_id: $vehicleId,
            title: $request->string('title')->toString(),
            description: $request->filled('description')
                ? $request->string('description')->toString() : null,
            odometer_reading: $request->filled('odometer_reading')
                ? (int) $request->input('odometer_reading') : null,
            labour_cost: (int) $request->input('labour_cost', 0),
        );
    }
}
