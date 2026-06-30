<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new mechanic. Authorization is handled by ManageMechanicPolicy in
 * the controller (against the tenant guard) — see MechanicController.
 *
 * A mechanic logs in with a PIN or a password, so AT LEAST ONE must be supplied.
 */
class StoreMechanicRequest extends FormRequest
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
            // Unique WITHIN the tenant. The unique rule runs a raw query that
            // bypasses TenantScope, so the tenant_id filter is applied explicitly.
            // whereNull(deleted_at) lets a soft-deleted mechanic's email be reused.
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('mechanics', 'email')
                    ->where('tenant_id', app('current_tenant')->id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:50'],
            // At least one credential — enforced via the required_without pair.
            'pin' => ['nullable', 'required_without:password', 'string', 'min:4', 'max:8'],
            'password' => ['nullable', 'required_without:pin', 'string', 'min:8', 'max:255'],
            'is_active' => ['boolean'],
        ];
    }
}
