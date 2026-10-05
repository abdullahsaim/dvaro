<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use App\Modules\SuperAdmin\Services\PlatformCredentialService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an update to the platform credentials (Super Admin → Settings →
 * Credentials). Every field is optional — a blank field means "leave this
 * credential unchanged", never "clear it" (see IntegrationCredentialsController
 * and PlatformCredentialService's docblocks). Explicit clearing goes through
 * the separate `clear` array.
 */
class IntegrationCredentialsRequest extends FormRequest
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
        $keys = PlatformCredentialService::allKeys();

        $rules = collect($keys)->mapWithKeys(fn (string $k) => [$k => ['nullable', 'string', 'max:500']])->all();

        $rules['smtp_port'] = ['nullable', 'integer', 'min:1', 'max:65535'];
        $rules['paypal_mode'] = ['nullable', Rule::in(['sandbox', 'live'])];
        $rules['smtp_encryption'] = ['nullable', Rule::in(['tls', 'ssl', 'none'])];

        $rules['clear'] = ['array'];
        $rules['clear.*'] = [Rule::in($keys)];

        return $rules;
    }
}
