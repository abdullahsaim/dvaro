<?php

namespace App\Http\Requests\Profile;

use Illuminate\Validation\Rule;

/**
 * Super-admin profile update (name + email). Unlike the three tenant-scoped
 * guards, super_admins has NO tenant_id — email is GLOBALLY unique (there is
 * one platform). Ignores the admin's own row; skips soft-deleted accounts so
 * a removed admin's address can be reused.
 */
class UpdateSuperAdminProfileRequest extends ProfileFormRequest
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
                Rule::unique('super_admins', 'email')
                    ->whereNull('deleted_at')
                    ->ignore($this->profileUser()->id),
            ],
        ];
    }
}
