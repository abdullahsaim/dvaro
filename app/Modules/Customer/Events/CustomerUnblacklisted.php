<?php

namespace App\Modules\Customer\Events;

use App\Modules\Customer\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer is removed from the blacklist, exclusively from
 * UnblacklistCustomerAction. Carries the reinstated customer.
 *
 * No listeners exist yet — they live in later sessions (audit log).
 */
class CustomerUnblacklisted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
    ) {}
}
