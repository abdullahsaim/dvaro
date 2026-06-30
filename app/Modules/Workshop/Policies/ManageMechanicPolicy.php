<?php

namespace App\Modules\Workshop\Policies;

use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;

/**
 * Authorization for tenant-admin management of Mechanic accounts.
 *
 * Distinct from MechanicPolicy (which authorizes SERVICE LOG actions for the
 * mechanic + tenant guards). This one governs the tenant app's Mechanic CRUD and
 * is TENANT-ADMIN ONLY — general staff/accounts roles cannot create or manage
 * workshop logins. Evaluated against the TENANT guard via
 * Gate::forUser(auth('tenant')->user()) in MechanicController.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: {mechanic} route-model
 * binding resolves through the global scope, so a cross-tenant mechanic is never
 * found (404) before a policy runs. The same-tenant assertion here is
 * defense-in-depth on top of the tenant_admin role gate.
 */
class ManageMechanicPolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return $this->isAdmin($user);
    }

    public function create(TenantUser $user): bool
    {
        return $this->isAdmin($user);
    }

    public function update(TenantUser $user, Mechanic $mechanic): bool
    {
        return $this->isAdmin($user) && $this->sameTenant($user, $mechanic);
    }

    public function delete(TenantUser $user, Mechanic $mechanic): bool
    {
        return $this->isAdmin($user) && $this->sameTenant($user, $mechanic);
    }

    private function isAdmin(TenantUser $user): bool
    {
        return $user->role === TenantUser::ROLE_ADMIN;
    }

    private function sameTenant(TenantUser $user, Mechanic $mechanic): bool
    {
        return (int) $user->tenant_id === (int) $mechanic->tenant_id;
    }
}
