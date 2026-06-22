<?php

namespace App\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a lead created by tenant staff. Authorization is handled by
 * LeadPolicy in the controller (against the tenant guard) — see LeadController.
 *
 * Only name/email/phone are required here — staff create the lead shell and the
 * customer fills the rest in via the intake form. token_expires_at is optional;
 * absent means the link never auto-expires.
 */
class StoreLeadRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'licence_number' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'rental_start_date' => ['nullable', 'date'],
            'rental_duration' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'token_expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }
}
