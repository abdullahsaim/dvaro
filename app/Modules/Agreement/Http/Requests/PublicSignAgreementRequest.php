<?php

namespace App\Modules\Agreement\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a signature submitted through the PUBLIC review-and-sign link.
 * No authorization check here — the token in the URL (verified by
 * AgreementSigningService::resolvePublic in the controller) IS the
 * credential; there is no tenant-guard user to authorize against.
 *
 * Same signature_data shape as the authenticated SignAgreementRequest (a
 * canvas data URL) — the draft-status guard lives in the controller instead,
 * since there is no route-bound Agreement model to inspect here.
 */
class PublicSignAgreementRequest extends FormRequest
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
            'signature_data' => ['required', 'string', 'regex:/^data:image\/[a-zA-Z0-9.+-]+;base64,/'],
        ];
    }

    public function messages(): array
    {
        return [
            'signature_data.regex' => __('agreement.invalid_signature'),
        ];
    }
}
