<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a mechanic edit. Authorization is handled by ManageMechanicPolicy in
 * the controller (against the tenant guard) — see MechanicController.
 *
 * Unlike the store request, pin/password are PURELY optional here: the mechanic
 * already has at least one credential, and a blank field means "keep it". So
 * there is no required_without pair — a blank pin AND blank password is valid and
 * simply leaves both stored hashes untouched (see UpdateMechanicDTO).
 */
class UpdateMechanicRequest extends FormRequest
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
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('mechanics', 'email')
                    ->where('tenant_id', app('current_tenant')->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('mechanic')->id),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            'pin' => ['nullable', 'string', 'min:4', 'max:8'],
            'password' => ['nullable', 'string', 'min:8', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
