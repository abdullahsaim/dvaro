<?php

namespace App\Modules\Customer\Actions;

use App\Actions\BaseAction;
use App\Modules\Customer\DTOs\UpdateCustomerDTO;
use App\Modules\Customer\Models\Customer;

/**
 * Updates a customer's editable attributes.
 *
 * Blacklist state is intentionally NOT updatable here — UpdateCustomerDTO
 * carries no blacklist fields, so this can never bypass Blacklist/
 * UnblacklistCustomerAction (the sanctioned paths that fire the blacklist
 * events).
 */
class UpdateCustomerAction extends BaseAction
{
    public function execute(Customer $customer, UpdateCustomerDTO $dto): Customer
    {
        $customer->update($dto->toAttributes());

        return $customer;
    }
}
