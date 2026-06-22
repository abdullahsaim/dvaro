<?php

namespace App\Modules\Agreement\Http\Requests;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a new agreement. Authorization is handled by AgreementPolicy in the
 * controller (against the tenant guard) — see AgreementController.
 *
 * Existence rules run raw query-builder SQL that bypasses TenantScope, so the
 * tenant_id filter is applied EXPLICITLY — otherwise a customer/vehicle id from
 * any tenant would validate.
 *
 * start_date is constrained to today-or-later HERE (the Store path only). New
 * versions are built server-side from an existing agreement and intentionally
 * bypass this request, so re-signing after a change never trips a past
 * start_date.
 */
class StoreAgreementRequest extends FormRequest
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
        $tenantId = app('current_tenant')->id;

        return [
            'customer_id' => [
                'required', 'integer',
                Rule::exists('customers', 'id')
                    ->where('tenant_id', $tenantId)
                    ->whereNull('deleted_at'),
            ],
            // Vehicle must exist for THIS tenant, be available, and not archived.
            'vehicle_id' => [
                'required', 'integer',
                Rule::exists('vehicles', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('status', Vehicle::STATUS_AVAILABLE)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', Rule::in(Agreement::TYPES)],
            'billing_cycle' => ['required', Rule::in(Agreement::BILLING_CYCLES)],
            'billing_cycle_day' => ['nullable', 'string', 'max:255'],
            'rate' => ['required', 'integer', 'min:1'],
            'bond_amount' => ['nullable', 'integer', 'min:0'],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'vehicle_id.exists' => __('agreement.vehicle_unavailable'),
        ];
    }
}
