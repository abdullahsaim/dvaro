<?php

namespace App\Modules\Customer\Events;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPortalInvitation;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant invites a customer to the Customer Portal, exclusively
 * from InviteCustomerToPortalAction. Carries the customer and the invitation
 * (whose token builds the public accept URL).
 *
 * No listener exists yet — the notification that emails/SMSes the invite link
 * is wired in a later Notification session, matching every other portal event.
 */
class CustomerPortalInvitationSent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Customer $customer,
        public readonly CustomerPortalInvitation $invitation,
    ) {}
}
