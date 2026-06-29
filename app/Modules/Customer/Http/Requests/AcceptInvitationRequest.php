<?php

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the password a customer sets when accepting a portal invitation.
 *
 * The invitation token itself is validated in the controller (scope-free lookup
 * by token AND explicit tenant_id, plus expiry/accepted checks) — this request
 * only governs the new password.
 */
class AcceptInvitationRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }
}
