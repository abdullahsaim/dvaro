<?php

namespace App\Modules\SaasCore\Http\Requests;

use App\Modules\Agreement\Models\AgreementTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Company profile. Admin-only — enforced in CompanyProfileController.
 * The ABN is checked for shape only (11 digits), not against the ABR.
 */
class CompanyProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('abn')) {
            $this->merge(['abn' => preg_replace('/\s+/', '', (string) $this->input('abn'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'abn' => ['nullable', 'string', 'regex:/^\d{11}$/'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'address' => ['nullable', 'string', 'max:500'],
            'brand_colour' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'default_state' => ['nullable', Rule::in(AgreementTemplate::STATES)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'abn.regex' => __('common.settings.abn_format'),
            'brand_colour.regex' => __('common.settings.colour_format'),
        ];
    }
}
