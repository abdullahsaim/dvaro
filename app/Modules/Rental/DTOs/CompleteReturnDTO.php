<?php

namespace App\Modules\Rental\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated vehicle-return data into RentalReturnService::completeReturn().
 *
 * deduction_amount and bond_amount are CENTS. bond_amount is NOT read from the
 * request — the service stamps it straight from the agreement at the moment of
 * return, so a client can never submit a different figure.
 */
class CompleteReturnDTO extends BaseDTO
{
    public function __construct(
        public readonly int $odometer_reading,
        public readonly string $fuel_level,
        public readonly ?string $condition_notes,
        public readonly bool $damage_found,
        public readonly ?string $damage_description,
        public readonly bool $needs_workshop,
        public readonly int $deduction_amount,
        public readonly ?string $deduction_reason,
        public readonly ?int $inspected_by,
    ) {}

    public static function fromRequest(Request $request, ?int $actingUserId): self
    {
        return new self(
            odometer_reading: (int) $request->input('odometer_reading'),
            fuel_level: $request->string('fuel_level')->toString(),
            condition_notes: $request->filled('condition_notes')
                ? $request->string('condition_notes')->toString() : null,
            damage_found: $request->boolean('damage_found'),
            damage_description: $request->filled('damage_description')
                ? $request->string('damage_description')->toString() : null,
            needs_workshop: $request->boolean('needs_workshop'),
            deduction_amount: (int) $request->input('deduction_amount', 0),
            deduction_reason: $request->filled('deduction_reason')
                ? $request->string('deduction_reason')->toString() : null,
            inspected_by: $actingUserId,
        );
    }
}
