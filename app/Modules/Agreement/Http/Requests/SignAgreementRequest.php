<?php

namespace App\Modules\Agreement\Http\Requests;

use App\Modules\Agreement\Models\Agreement;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a signing request. Authorization is handled by AgreementPolicy in
 * the controller (against the tenant guard) — see AgreementController.
 *
 * signature_data must be a canvas data URL (a base64 image). The regex guard
 * keeps garbage out of the stored signature; the value is otherwise persisted
 * as-is.
 *
 * The draft-status guard is also enforced here so a non-draft agreement yields a
 * proper Inertia validation error (redirect-back) rather than the raw 422 JSON
 * that AgreementService::sign() would throw. The service still re-guards as the
 * authority — this is a UX-friendly first line, not the only one.
 */
class SignAgreementRequest extends FormRequest
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
            // Canvas signatures are always a data URL (e.g. data:image/png;base64,...).
            'signature_data' => ['required', 'string', 'regex:/^data:image\/[a-zA-Z0-9.+-]+;base64,/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $agreement = $this->route('agreement');

            if ($agreement instanceof Agreement && $agreement->status !== Agreement::STATUS_DRAFT) {
                $validator->errors()->add('signature_data', __('agreement.not_signable'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'signature_data.regex' => __('agreement.invalid_signature'),
        ];
    }
}
