<?php

namespace App\Modules\SaasCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * The invitee choosing their own password. PUBLIC route (no auth, no bound
 * user) — the invitation token is the only credential, checked in the
 * controller before this is ever reached.
 */
class AcceptStaffInvitationRequest extends FormRequest
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
            'password' => ['required', 'confirmed', Password::min(8)],
        ];
    }
}
