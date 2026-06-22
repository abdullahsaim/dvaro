<?php

namespace App\Modules\Customer\Actions;

use App\Actions\BaseAction;
use App\Modules\Customer\Events\CustomerBlacklisted;
use App\Modules\Customer\Models\Customer;

/**
 * Blacklists a customer with a recorded reason. The ONLY sanctioned path to set
 * a customer's blacklist state (fires CustomerBlacklisted); never mutate
 * is_blacklisted directly elsewhere.
 */
class BlacklistCustomerAction extends BaseAction
{
    public function execute(Customer $customer, string $reason): Customer
    {
        $customer->update([
            'is_blacklisted' => true,
            'blacklisted_reason' => $reason,
        ]);

        CustomerBlacklisted::dispatch($customer, $reason);

        return $customer;
    }
}
