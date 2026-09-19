<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a new service log opened from the mechanic portal.
 *
 * The vehicle is resolved from the {token} route segment (not the request body),
 * and mechanic_id comes from the authenticated guard — neither is trusted from
 * input. labour_cost is entered/stored in CENTS.
 */
class CreateServiceLogRequest extends FormRequest
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
            // Target vehicle: id (vehicle page) or legacy QR token. Existence is
            // checked tenant-scoped in VehicleLookupService (cross-tenant → 404).
            'vehicle_id' => ['required_without:token', 'nullable', 'integer'],
            'token' => ['required_without:vehicle_id', 'nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'odometer_reading' => ['nullable', 'integer', 'min:0'],
            'labour_cost' => ['nullable', 'integer', 'min:0'],
            // Only a scheduled service resets the vehicle's service schedule.
            'is_scheduled_service' => ['nullable', 'boolean'],
        ];
    }
}
