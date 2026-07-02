<?php

namespace App\Modules\SaasCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a tenant admin's plan-upgrade request.
 *
 * Authorization (tenant-admin only) is enforced in the controller via
 * Gate::forUser(auth('tenant')->user())->authorize('requestUpgrade'), so
 * authorize() here stays true — $this->user() would resolve the empty web guard.
 */
class UpgradeRequestRequest extends FormRequest
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
            // Must be a real, ACTIVE plan (an inactive plan is not on offer).
            'requested_plan_id' => [
                'required',
                'integer',
                Rule::exists('plans', 'id')->where('is_active', true),
            ],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
