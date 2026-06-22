<?php

namespace App\Modules\Customer\Events;

use App\Modules\Customer\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once when a customer is added to a tenant, exclusively from
 * CreateCustomerAction. Carries the newly created customer.
 *
 * No listeners exist yet — they live in later sessions (welcome notification,
 * audit log, CRM lead-conversion follow-up).
 */
class CustomerCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
    ) {}
}
