<?php

namespace App\Modules\Customer\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Validates a customer edit. Same fields as StoreCustomerRequest EXCEPT the
 * email unique rule ignores the customer being edited.
 */
class UpdateCustomerRequest extends StoreCustomerRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $customerId = $this->route('customer')->id;

        $rules['email'] = [
            'required', 'email', 'max:255',
            Rule::unique('customers', 'email')
                ->where('tenant_id', app('current_tenant')->id)
                ->whereNull('deleted_at')
                ->ignore($customerId),
        ];

        return $rules;
    }
}
