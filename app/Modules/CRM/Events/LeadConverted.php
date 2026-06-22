<?php

namespace App\Modules\CRM\Events;

use App\Modules\CRM\Models\Lead;
use App\Modules\Customer\Models\Customer;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a lead is converted into a Customer (ConvertLeadAction). Carries
 * both the originating lead and the new customer.
 *
 * No listeners yet (Notification / audit sessions). NOTE: CreateCustomerAction
 * separately fires CustomerCreated for the new Customer — this event is the
 * CRM-side signal that a conversion specifically happened.
 */
class LeadConverted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
        public readonly Customer $customer,
    ) {}
}
