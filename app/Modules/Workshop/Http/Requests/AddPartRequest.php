<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a spare part added to a service log. unit_cost is in CENTS;
 * total_cost is derived server-side (quantity × unit_cost) and never trusted
 * from input.
 */
class AddPartRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_cost' => ['required', 'integer', 'min:0'],
        ];
    }
}
