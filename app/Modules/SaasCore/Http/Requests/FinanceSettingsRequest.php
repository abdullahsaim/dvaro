<?php

namespace App\Modules\SaasCore\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Invoicing & late-fee settings. Money is in CENTS (the form converts from
 * AUD). Admin/accounts only — enforced in FinanceSettingsController.
 */
class FinanceSettingsRequest extends FormRequest
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
            'late_fees_enabled' => ['required', 'boolean'],
            'late_fee_grace_days' => ['required', 'integer', 'min:0', 'max:90'],
            'late_fee_type' => ['required', Rule::in(['fixed', 'percentage'])],
            // Each amount is required only for the type in use, so switching
            // type is never blocked by a stale value in the other field.
            'late_fee_amount' => ['nullable', 'required_if:late_fee_type,fixed', 'integer', 'min:0', 'max:1000000'],
            'late_fee_percentage' => ['nullable', 'required_if:late_fee_type,percentage', 'integer', 'min:0', 'max:100'],
            'invoice_prefix' => ['nullable', 'string', 'max:10'],
            'invoice_payment_terms_days' => ['required', 'integer', 'min:0', 'max:180'],
            'invoice_footer_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
