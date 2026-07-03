<?php

namespace App\Http\Requests\Profile;

use Illuminate\Validation\Rule;

/**
 * Mechanic-guard profile update (name + email). Email is unique per tenant
 * (explicit tenant_id — the unique rule bypasses TenantScope), ignores the
 * mechanic's own row, and skips soft-deleted mechanics so an archived
 * account's address can be reused (same convention as UpdateMechanicRequest
 * in the tenant-admin CRUD).
 */
class UpdateMechanicProfileRequest extends ProfileFormRequest
{
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
                    ->ignore($this->profileUser()->id),
            ],
        ];
    }
}
