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
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'odometer_reading' => ['nullable', 'integer', 'min:0'],
            'labour_cost' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
