<?php

namespace App\Modules\Agreement\Http\Requests;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Models\AgreementTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / edit agreement terms. The BODY is sanitised and its merge fields
 * validated in AgreementTemplateService::save() (one place, shared by the
 * tenant and super-admin paths) — this only checks shape and size.
 * Authorization: AgreementTemplatePolicy in the controller.
 */
class AgreementTemplateRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'agreement_type' => ['nullable', Rule::in(Agreement::TYPES)],
            'state' => ['nullable', Rule::in(AgreementTemplate::STATES)],
            'body_html' => ['required', 'string', 'max:200000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
