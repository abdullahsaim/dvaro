<?php

namespace App\Modules\Workshop\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated future-booking data into ScheduleServiceAction.
 *
 * Booked from the tenant-admin Workshop screen (not the mechanic portal) — an
 * admin assigns the mechanic up front, unlike an ad-hoc log where the acting
 * mechanic IS the log's mechanic. No tenant_id (HasTenant fills it).
 */
class ScheduleServiceDTO extends BaseDTO
{
    public function __construct(
        public readonly int $vehicle_id,
        public readonly int $mechanic_id,
        public readonly string $title,
        public readonly string $scheduled_for,
        public readonly ?string $description = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            vehicle_id: (int) $request->input('vehicle_id'),
            mechanic_id: (int) $request->input('mechanic_id'),
            title: $request->string('title')->toString(),
            scheduled_for: $request->string('scheduled_for')->toString(),
            description: $request->filled('description')
                ? $request->string('description')->toString() : null,
        );
    }
}
