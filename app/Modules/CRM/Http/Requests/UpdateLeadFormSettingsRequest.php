<?php

namespace App\Modules\CRM\Http\Requests;

use App\Modules\CRM\Services\LeadFormService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Public lead-form settings. tenant_admin only — enforced in LeadFormController.
 * allowed_domains: websites allowed to embed the form (empty = any website).
 */
class UpdateLeadFormSettingsRequest extends FormRequest
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
            'enabled' => ['required', 'boolean'],
            'intro' => ['nullable', 'string', 'max:500'],
            'allowed_domains' => ['nullable', 'array', 'max:'.LeadFormService::MAX_ALLOWED_DOMAINS],
            'allowed_domains.*' => [
                'string', 'max:255',
                function (string $attribute, mixed $value, Closure $fail) {
                    if (LeadFormService::normalizeOrigin((string) $value) === null) {
                        $fail(__('common.crm.lead_form_bad_domain', ['domain' => $value]));
                    }
                },
            ],
        ];
    }
}
