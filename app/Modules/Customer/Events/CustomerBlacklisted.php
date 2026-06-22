<?php

namespace App\Modules\Customer\Events;

use App\Modules\Customer\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a customer is blacklisted, exclusively from
 * BlacklistCustomerAction. Carries the customer and the reason given.
 *
 * No listeners exist yet — they live in later sessions (audit log, notify
 * staff, block future bookings).
 */
class CustomerBlacklisted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly string $reason,
    ) {}
}
