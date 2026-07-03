<?php

namespace App\Http\Requests\Profile;

use Illuminate\Validation\Rule;

/**
 * Tenant-guard profile update (name + email). Email is unique PER TENANT —
 * scoped EXPLICITLY to tenant_id because the unique rule runs raw
 * query-builder SQL that bypasses TenantScope — and ignores the user's own
 * row so re-saving an unchanged email always passes. The same address may
 * exist under a different tenant (mirrors registration/login).
 * tenant_users has no soft deletes, so no deleted_at filter is needed.
 */
class UpdateTenantProfileRequest extends ProfileFormRequest
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
                Rule::unique('tenant_users', 'email')
                    ->where('tenant_id', app('current_tenant')->id)
                    ->ignore($this->profileUser()->id),
            ],
        ];
    }
}
