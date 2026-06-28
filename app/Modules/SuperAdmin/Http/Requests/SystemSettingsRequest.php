<?php

namespace App\Modules\SuperAdmin\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates an update to the platform settings (super admin).
 *
 * Each field maps to one platform_settings row; the controller persists them
 * via PlatformSettingsService::set() (which casts + busts cache per key).
 */
class SystemSettingsRequest extends FormRequest
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
            'platform_name' => ['required', 'string', 'max:255'],
            'support_email' => ['required', 'email', 'max:255'],

            'manual_tenant_approval' => ['boolean'],
            'free_trial_enabled' => ['boolean'],
            'free_trial_days' => ['required', 'integer', 'min:0'],
            'freemium_enabled' => ['boolean'],
            'maintenance_mode' => ['boolean'],

            // Fallback plan when no paid plan is available. Nullable.
            'default_plan_id' => ['nullable', 'integer', Rule::exists('plans', 'id')],
            // Hard cap on total tenants. Null = unlimited.
            'max_tenants' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
