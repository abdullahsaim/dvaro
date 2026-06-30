<?php

namespace App\Modules\CMS\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a public "request a demo" submission from the landing site.
 *
 * PUBLIC: no authentication. Spam is curbed by the per-IP route throttle
 * (3/hour). authorize() returns true — anyone may submit.
 */
class DemoRequestFormRequest extends FormRequest
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
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
