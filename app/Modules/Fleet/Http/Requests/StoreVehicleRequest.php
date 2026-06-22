<?php

namespace App\Modules\Fleet\Http\Requests;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new vehicle. Authorization is handled by VehiclePolicy in the
 * controller (against the tenant guard) — see FleetController.
 */
class StoreVehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Unique WITHIN the tenant. The unique rule runs a raw query-builder
            // query that bypasses TenantScope, so the tenant_id filter MUST be
            // applied explicitly here — otherwise it would be unique platform-wide.
            'registration_number' => [
                'required', 'string', 'max:255',
                Rule::unique('vehicles', 'registration_number')
                    ->where('tenant_id', app('current_tenant')->id),
            ],
            'make' => ['required', 'string', 'max:255'],
            'model' => ['required', 'string', 'max:255'],
            'year' => ['required', 'integer', 'min:1900', 'max:'.(date('Y') + 1)],
            // Stored as cents (integer). UI dollar-formatting is a later design pass.
            'daily_rate' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
            'insurance_company' => ['nullable', 'string', 'max:255'],
            'insurance_expiry' => ['nullable', 'date'],
            'registration_expiry' => ['nullable', 'date'],
            'last_service_date' => ['nullable', 'date'],
            'next_service_due' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
