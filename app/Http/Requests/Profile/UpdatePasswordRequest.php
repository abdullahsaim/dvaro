<?php

namespace App\Http\Requests\Profile;

/**
 * Change-password validation, shared verbatim by all four guards (the password
 * column is identical everywhere). Identity is proven via Hash::check against
 * the stored credential (see ProfileFormRequest::currentCredentialRule) —
 * never Auth::attempt. The new password is hashed by each model's 'hashed'
 * cast on save.
 */
class UpdatePasswordRequest extends ProfileFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', $this->currentCredentialRule()],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed'],
        ];
    }
}
