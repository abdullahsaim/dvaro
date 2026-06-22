<?php

namespace App\Modules\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the PUBLIC intake form submission.
 *
 * Unauthenticated by design — authorize() returns true; access is gated by the
 * signed URL + lead-expiry checks in IntakeFormController, and abuse is bounded
 * by the throttle:crm-intake limiter on the route (5/hour per token).
 *
 * Only name/phone are required — email is nullable here because some walk-in
 * customers genuinely don't have one. Everything else is best-effort: the
 * customer fills in what they can.
 */
class IntakeFormRequest extends FormRequest
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
            'phone' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'licence_number' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
            'rental_start_date' => ['nullable', 'date'],
            'rental_duration' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
