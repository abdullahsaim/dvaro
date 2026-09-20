<?php

namespace App\Modules\SaasCore\Http\Requests;

use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invite a staff member. Duplicate-email and plan-limit checks live in
 * InviteStaffAction (they need the tenant's existing users AND pending
 * invitations). Admin-only — enforced in StaffController.
 */
class InviteStaffRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in(TenantUser::ROLES)],
        ];
    }
}
