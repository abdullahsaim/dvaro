<?php

namespace App\Http\Requests\Profile;

use Illuminate\Validation\Rule;

/**
 * Customer-portal profile update — EMAIL ONLY. CustomerUser deliberately has
 * no name column: the display name lives on the linked Customer profile/ledger
 * record and changes only through the tenant admin's customer management UI,
 * never from the portal.
 *
 * Email is unique per tenant (explicit tenant_id — the unique rule bypasses
 * TenantScope), ignores the user's own row, and skips soft-deleted accounts
 * so a removed login's address can be reused (same convention as
 * StoreCustomerRequest / StoreMechanicRequest).
 */
class UpdateCustomerProfileRequest extends ProfileFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('customer_users', 'email')
                    ->where('tenant_id', app('current_tenant')->id)
                    ->whereNull('deleted_at')
                    ->ignore($this->profileUser()->id),
            ],
        ];
    }
}
