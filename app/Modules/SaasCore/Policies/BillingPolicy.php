<?php

namespace App\Modules\SaasCore\Policies;

use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for the tenant billing portal — TENANT-ADMIN ONLY.
 *
 * General staff / accounts / mechanic roles cannot view billing or request
 * upgrades. Evaluated against the TENANT guard via
 * Gate::forUser(auth('tenant')->user()) in BillingController /
 * UpgradeRequestController. Mirrors ManageMechanicPolicy's admin-only model.
 *
 * These are model-less abilities (there is no Billing model), so this policy is
 * registered with Gate::define delegations in AppServiceProvider, not
 * Gate::policy.
 */
class BillingPolicy
{
    public function view(TenantUser $user): bool
    {
        return $this->isAdmin($user);
    }

    public function requestUpgrade(TenantUser $user): bool
    {
        return $this->isAdmin($user);
    }

    private function isAdmin(TenantUser $user): bool
    {
        return $user->role === TenantUser::ROLE_ADMIN;
    }
}
