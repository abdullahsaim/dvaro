<?php

namespace App\Modules\Fleet\DTOs;

use App\DTOs\BaseDTO;
use Illuminate\Http\Request;

/**
 * Carries validated new-vehicle data into CreateVehicleAction.
 *
 * No tenant_id (HasTenant auto-populates it from the bound tenant on create)
 * and no qr_code_token (token generation is a later Workshop/QR session).
 * daily_rate is in CENTS — matches the vehicles.daily_rate column.
 */
class CreateVehicleDTO extends BaseDTO
{
    public function __construct(
        public readonly string $registration_number,
        public readonly string $make,
        public readonly string $model,
        public readonly int $year,
        public readonly string $status,
        public readonly int $daily_rate,
        public readonly ?string $insurance_company = null,
        public readonly ?string $insurance_expiry = null,
        public readonly ?string $registration_expiry = null,
        public readonly ?string $last_service_date = null,
        public readonly ?string $next_service_due = null,
        public readonly ?string $notes = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            registration_number: $request->string('registration_number')->toString(),
            make: $request->string('make')->toString(),
            model: $request->string('model')->toString(),
            year: (int) $request->input('year'),
            status: $request->string('status')->toString(),
            daily_rate: (int) $request->input('daily_rate'),
            insurance_company: $request->filled('insurance_company')
                ? $request->string('insurance_company')->toString() : null,
            insurance_expiry: $request->filled('insurance_expiry')
                ? $request->string('insurance_expiry')->toString() : null,
            registration_expiry: $request->filled('registration_expiry')
                ? $request->string('registration_expiry')->toString() : null,
            last_service_date: $request->filled('last_service_date')
                ? $request->string('last_service_date')->toString() : null,
            next_service_due: $request->filled('next_service_due')
                ? $request->string('next_service_due')->toString() : null,
            notes: $request->filled('notes') ? $request->string('notes')->toString() : null,
        );
    }

    /**
     * Attributes for Vehicle::create(). Keys map 1:1 to fillable columns.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'registration_number' => $this->registration_number,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'status' => $this->status,
            'daily_rate' => $this->daily_rate,
            'insurance_company' => $this->insurance_company,
            'insurance_expiry' => $this->insurance_expiry,
            'registration_expiry' => $this->registration_expiry,
            'last_service_date' => $this->last_service_date,
            'next_service_due' => $this->next_service_due,
            'notes' => $this->notes,
        ];
    }
}
