<?php

namespace App\Modules\CMS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public contact-form submission from the landing site.
 *
 * Contact enquiries are persisted as DemoRequest rows (no separate contacts
 * table) so they surface in the same super admin queue: the controller maps
 * name → contact_name and stores the enquiry in `message`.
 *
 * PUBLIC: no auth; curbed by the per-IP route throttle (3/hour).
 */
class ContactFormRequest extends FormRequest
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
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
