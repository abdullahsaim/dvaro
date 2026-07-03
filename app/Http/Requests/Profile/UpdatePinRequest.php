<?php

namespace App\Http\Requests\Profile;

/**
 * Mechanic PIN change (mechanic guard only — no other guard has a PIN).
 * Identity is verified BEFORE the PIN may change: current password or current
 * PIN via Hash::check (a PIN-only mechanic has no password to present). The
 * new PIN is digits only, 4–6 long, and is hashed by the Mechanic model's
 * 'hashed' cast on save.
 */
class UpdatePinRequest extends ProfileFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string', $this->currentCredentialRule()],
            'pin' => ['required', 'digits_between:4,6', 'confirmed'],
        ];
    }
}
