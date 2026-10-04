<?php

namespace App\Modules\Rental\Http\Requests;

use App\Modules\Rental\Models\ReturnInspection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a vehicle-return submission. Authorization is enforced in the
 * controller via Gate::forUser(auth('tenant')->user())->authorize('returnVehicle', ...)
 * — not here — matching the rest of the Agreement module.
 */
class StoreReturnInspectionRequest extends FormRequest
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
            'odometer_reading' => ['required', 'integer', 'min:0'],
            'fuel_level' => ['required', Rule::in(ReturnInspection::FUEL_LEVELS)],
            'condition_notes' => ['nullable', 'string', 'max:2000'],
            'damage_found' => ['boolean'],
            'damage_description' => ['required_if:damage_found,true', 'nullable', 'string', 'max:2000'],
            'needs_workshop' => ['boolean'],
            'deduction_amount' => ['nullable', 'integer', 'min:0'],
            'deduction_reason' => [
                Rule::requiredIf((int) $this->input('deduction_amount', 0) > 0),
                'nullable',
                'string',
                'max:2000',
            ],
        ];
    }
}
