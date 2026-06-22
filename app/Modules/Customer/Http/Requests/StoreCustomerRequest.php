<?php

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new customer. Authorization is handled by CustomerPolicy in the
 * controller (against the tenant guard) — see CustomerController.
 */
class StoreCustomerRequest extends FormRequest
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
            // Unique WITHIN the tenant. The unique rule runs a raw query-builder
            // query that bypasses TenantScope, so the tenant_id filter MUST be
            // applied explicitly here — otherwise it would be unique platform-wide.
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('customers', 'email')
                    ->where('tenant_id', app('current_tenant')->id)
                    ->whereNull('deleted_at'),
            ],
            'phone' => ['required', 'string', 'max:255'],
            'licence_number' => ['required', 'string', 'max:255'],
            'licence_expiry' => ['nullable', 'date'],
            'passport_number' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'emergency_contact_name' => ['required', 'string', 'max:255'],
            'emergency_contact_phone' => ['required', 'string', 'max:255'],
            'risk_notes' => ['nullable', 'string'],
        ];
    }
}
