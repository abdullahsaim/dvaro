<?php

namespace App\Modules\Customer\Actions;

use App\Actions\BaseAction;
use App\Modules\Customer\Events\CustomerPortalInvitationSent;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPortalInvitation;
use App\Modules\Customer\Models\CustomerUser;
use Illuminate\Support\Str;

/**
 * Invites a customer to the Customer Portal — the ONE sanctioned path. Creates a
 * signed, 7-day invitation whose token builds the public accept URL, and fires
 * CustomerPortalInvitationSent (the notification listener lands in a later
 * session, matching every other portal event).
 *
 * Idempotent on two fronts:
 *   - if the customer already has a portal login (CustomerUser), no new invite
 *     is ever issued — their most recent invitation is returned untouched;
 *   - otherwise an outstanding (unaccepted, unexpired) invitation is reused
 *     rather than minting a fresh token on every click.
 *
 * Runs inside a bound-tenant web request, so tenant_id is set explicitly from the
 * customer (robust even though HasTenant would auto-fill from the bound tenant).
 */
class InviteCustomerToPortalAction extends BaseAction
{
    public function execute(Customer $customer): CustomerPortalInvitation
    {
        // Already has portal access → never re-invite; return the latest invite.
        if (CustomerUser::where('customer_id', $customer->id)->exists()) {
            $existing = CustomerPortalInvitation::where('customer_id', $customer->id)
                ->latest('id')
                ->first();

            if ($existing !== null) {
                return $existing;
            }
        }

        // Reuse an outstanding invitation rather than issuing a duplicate token.
        $pending = CustomerPortalInvitation::where('customer_id', $customer->id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        $invitation = CustomerPortalInvitation::create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'token' => (string) Str::uuid(),
            'expires_at' => now()->addDays(7),
        ]);

        CustomerPortalInvitationSent::dispatch($customer, $invitation);

        return $invitation;
    }
}
