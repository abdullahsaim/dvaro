<?php

namespace App\Modules\Invoice\Http\Requests;

use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a mid-cycle vehicle change (preview and confirm both flow through
 * here). Authorization is handled in the controller via AgreementPolicy
 * (createVersion ability) against the tenant guard.
 *
 * The existence rule runs raw query-builder SQL that bypasses TenantScope, so the
 * tenant_id filter is applied EXPLICITLY — otherwise a vehicle id from any tenant
 * would validate. The vehicle must additionally be available and not archived.
 *
 * `confirm` distinguishes the two-step flow: absent/false → preview (pure
 * calculation, no writes); true → execute the change.
 */
class ChangeVehicleRequest extends FormRequest
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
            'new_vehicle_id' => [
                'required', 'integer',
                Rule::exists('vehicles', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('status', Vehicle::STATUS_AVAILABLE)
                    ->whereNull('deleted_at'),
            ],
            'change_date' => ['required', 'date'],
            'confirm' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_vehicle_id.exists' => __('invoice.vehicle_unavailable'),
        ];
    }

    /** Whether this is the confirm step (otherwise a preview). */
    public function isConfirmed(): bool
    {
        return $this->boolean('confirm');
    }
}
