<?php

namespace App\Modules\Customer\Policies;

use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for Customer actions.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: route-model binding
 * for {customer} resolves through the global scope, so a customer belonging to
 * another tenant is never found (404) before a policy ever runs. This policy is
 * explicit defense-in-depth — it asserts the acting tenant user and the target
 * customer belong to the same tenant.
 *
 * IMPORTANT: these checks must be evaluated against the TENANT guard user. The
 * controller calls Gate::forUser(auth('tenant')->user())->authorize(...) for
 * exactly this reason — see CustomerController.
 */
class CustomerPolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function create(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer);
    }

    public function update(TenantUser $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer);
    }

    public function delete(TenantUser $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer);
    }

    public function blacklist(TenantUser $user, Customer $customer): bool
    {
        return $this->sameTenant($user, $customer);
    }

    private function sameTenant(TenantUser $user, Customer $customer): bool
    {
        return (int) $user->tenant_id === (int) $customer->tenant_id;
    }
}
