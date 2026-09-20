<?php

namespace App\Modules\SaasCore\Http\Requests;

use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Change a staff member's role and/or active status. Both are optional (the UI
 * sends one at a time). The lock-yourself-out guardrails live in
 * UpdateStaffAction. Admin-only — enforced in StaffController.
 */
class UpdateStaffRequest extends FormRequest
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
            'role' => ['nullable', Rule::in(TenantUser::ROLES)],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
