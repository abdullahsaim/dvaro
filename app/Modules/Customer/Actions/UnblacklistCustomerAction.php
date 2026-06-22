<?php

namespace App\Modules\Customer\Actions;

use App\Actions\BaseAction;
use App\Modules\Customer\Events\CustomerUnblacklisted;
use App\Modules\Customer\Models\Customer;

/**
 * Removes a customer from the blacklist and clears the recorded reason. The
 * ONLY sanctioned path to lift blacklist state (fires CustomerUnblacklisted);
 * never mutate is_blacklisted directly elsewhere.
 */
class UnblacklistCustomerAction extends BaseAction
{
    public function execute(Customer $customer): Customer
    {
        $customer->update([
            'is_blacklisted' => false,
            'blacklisted_reason' => null,
        ]);

        CustomerUnblacklisted::dispatch($customer);

        return $customer;
    }
}
