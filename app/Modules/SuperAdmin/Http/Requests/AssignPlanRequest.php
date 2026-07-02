<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use App\Modules\SaasCore\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a super admin's manual plan assignment to a tenant.
 *
 * Authorization ('supportAccess') is enforced in the controller via
 * Gate::forUser(auth('superadmin')->user()); authorize() here stays true.
 * A super admin may assign ANY plan (active or not) — this is a support action.
 */
class AssignPlanRequest extends FormRequest
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
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'billing_cycle' => [
                'required',
                Rule::in([Subscription::BILLING_MONTHLY, Subscription::BILLING_ANNUAL]),
            ],
        ];
    }
}
