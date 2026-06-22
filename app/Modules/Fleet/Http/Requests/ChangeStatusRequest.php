<?php

namespace App\Modules\Fleet\Http\Requests;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a vehicle status transition. The status must be one of the six
 * Vehicle::STATUSES — this guarantees ChangeVehicleStatusAction (which also
 * guards STATUSES) is never reached with an invalid value.
 *
 * Authorization is handled by VehiclePolicy in the controller (tenant guard).
 */
class ChangeStatusRequest extends FormRequest
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
            'status' => ['required', Rule::in(Vehicle::STATUSES)],
        ];
    }
}
