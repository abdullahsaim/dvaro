<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\ProfileController;
use App\Http\Requests\Profile\UpdateCustomerProfileRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Profile settings for the customer portal (the customer guard).
 *
 * SPECIAL CASE: CustomerUser has no name — the display name lives on the
 * linked Customer profile/ledger record, shown here READ-ONLY. Name changes
 * go through the tenant admin's customer management UI only, so the portal
 * update touches email alone.
 */
class CustomerProfileController extends ProfileController
{
    protected function guard(): string
    {
        return 'customer';
    }

    protected function profileView(): string
    {
        return 'Profile/CustomerProfile';
    }

    protected function profileProps(): array
    {
        $user = $this->profileUser();

        return [
            'user' => [
                'email' => $user->email,
            ],
            // Read-only, from the linked Customer record (tenant is bound on
            // this route, so the relation resolves through TenantScope).
            'customerName' => $user->customer?->name,
        ];
    }

    public function updateProfile(UpdateCustomerProfileRequest $request): RedirectResponse
    {
        return $this->applyProfileUpdate($request->safe()->only(['email']));
    }
}
