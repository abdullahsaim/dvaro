<?php

namespace App\Modules\Workshop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates a future service booking from the tenant-admin Workshop screen.
 *
 * vehicle_id/mechanic_id EXPLICITLY scoped to tenant_id — the `exists` rule
 * runs a raw query-builder SELECT that bypasses TenantScope entirely, so
 * without the explicit ->where() this would validate against ANY tenant's
 * vehicle/mechanic id, not just the bound tenant's (same footgun documented
 * on StoreAgreementRequest).
 */
class ScheduleServiceRequest extends FormRequest
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
            'vehicle_id' => ['required', 'integer', Rule::exists('vehicles', 'id')->where('tenant_id', $tenantId)],
            'mechanic_id' => ['required', 'integer', Rule::exists('mechanics', 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'scheduled_for' => ['required', 'date', 'after:now'],
        ];
    }
}
