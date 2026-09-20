<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Modules\Invoice\Services\InvoiceTemplateService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The invoice template form. Authorization (admin or accounts) is enforced in
 * InvoiceTemplateController against the tenant guard.
 *
 * These rules give the person useful errors; InvoiceTemplateService::sanitize()
 * then re-checks everything before it is stored, so a hand-crafted request can
 * never widen what the form controls.
 */
class InvoiceTemplateRequest extends FormRequest
{
    /** Matches InvoiceTemplateController::LOGO_MAX_KB. */
    public const LOGO_MAX_KB = 2048;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // The logo upload posts to its own endpoint with only a file, so it is
        // validated on its own terms — the template fields are not part of it.
        if ($this->is('*/settings/invoice-template/logo')) {
            return [
                'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.self::LOGO_MAX_KB],
            ];
        }

        $limits = InvoiceTemplateService::LIMITS;

        return [
            'layout' => ['required', Rule::in(InvoiceTemplateService::LAYOUTS)],
            'accent_colour' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'show_logo' => ['required', 'boolean'],
            'show_company_details' => ['required', 'boolean'],
            'gst_registered' => ['required', 'boolean'],
            'title' => ['nullable', 'string', 'max:'.$limits['title']],
            'intro' => ['nullable', 'string', 'max:'.$limits['intro']],
            'payment_instructions' => ['nullable', 'string', 'max:'.$limits['payment_instructions']],
            'footer_note' => ['nullable', 'string', 'max:'.$limits['footer_note']],
            'thank_you' => ['nullable', 'string', 'max:'.$limits['thank_you']],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'accent_colour.regex' => __('common.settings.colour_format'),
        ];
    }
}
