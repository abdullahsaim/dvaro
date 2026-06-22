<?php

namespace App\Modules\Fleet\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates a vehicle edit. Same fields as StoreVehicleRequest EXCEPT:
 *   - the registration_number unique rule ignores the vehicle being edited
 *   - there is NO status field (status changes go through the separate
 *     changeStatus endpoint / ChangeVehicleStatusAction)
 */
class UpdateVehicleRequest extends StoreVehicleRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $vehicleId = $this->route('vehicle')->id;

        $rules['registration_number'] = [
            'required', 'string', 'max:255',
            Rule::unique('vehicles', 'registration_number')
                ->where('tenant_id', app('current_tenant')->id)
                ->ignore($vehicleId),
        ];

        // Status is never edited here.
        unset($rules['status']);

        return $rules;
    }
}
