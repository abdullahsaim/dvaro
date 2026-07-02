<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use App\Modules\SaasCore\Models\Subscription;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a super admin completing an upgrade request — assigns the requested
 * plan to the tenant on the chosen billing cycle. Authorization ('supportAccess')
 * is enforced in the controller.
 */
class CompleteUpgradeRequest extends FormRequest
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
            'billing_cycle' => [
                'required',
                Rule::in([Subscription::BILLING_MONTHLY, Subscription::BILLING_ANNUAL]),
            ],
        ];
    }
}
