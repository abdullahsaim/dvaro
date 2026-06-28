<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use App\Modules\SaasCore\Models\Plan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates creation of a subscription plan (super admin).
 *
 * Pricing is submitted as integer CENTS (the Vue form converts AUD → cents,
 * mirroring the agreement/invoice flow). Modules and limit keys are constrained
 * to the known sets on the Plan model so the stored config can never reference
 * an unknown module or limit.
 */
class StorePlanRequest extends FormRequest
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
            'description' => ['nullable', 'string', 'max:2000'],

            // Cents — non-negative integers.
            'price_monthly' => ['required', 'integer', 'min:0'],
            'price_annual' => ['required', 'integer', 'min:0'],

            'is_active' => ['boolean'],
            'is_free' => ['boolean'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0'],

            // Module checklist — each value must be a known module key.
            'modules' => ['array'],
            'modules.*' => ['string', Rule::in(Plan::MODULE_KEYS)],

            // Limits — only known keys; -1 (or any negative) means unlimited.
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:-1'],
        ];
    }

    /**
     * Drop any limit keys that aren't recognised, so the stored config stays
     * clean. (Rule::in on array KEYS is awkward; filtering here is simpler.)
     */
    protected function prepareForValidation(): void
    {
        $limits = (array) $this->input('limits', []);

        $this->merge([
            'limits' => array_intersect_key($limits, array_flip(Plan::LIMIT_KEYS)),
        ]);
    }
}
