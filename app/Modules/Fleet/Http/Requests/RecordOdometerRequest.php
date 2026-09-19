<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a manual odometer reading. The "never backwards" rule is enforced
 * in RecordOdometerReadingAction (it needs the locked current value).
 * Authorization is handled by VehiclePolicy in the controller.
 */
class RecordOdometerRequest extends FormRequest
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
            'reading' => ['required', 'integer', 'min:0', 'max:9999999'],
        ];
    }
}
