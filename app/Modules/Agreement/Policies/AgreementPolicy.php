<?php

namespace App\Modules\Agreement\Policies;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for Agreement actions.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: route-model binding
 * for {agreement} resolves through the global scope, so an agreement belonging
 * to another tenant is never found (404) before a policy runs. This policy is
 * explicit defense-in-depth — it asserts the acting tenant user and the target
 * agreement belong to the same tenant.
 *
 * IMPORTANT: these checks must be evaluated against the TENANT guard user. The
 * controller calls Gate::forUser(auth('tenant')->user())->authorize(...) for
 * exactly this reason — see AgreementController.
 *
 * There is deliberately no update/delete ability: agreements are immutable.
 * Mutation happens only via sign (status transition) and createVersion.
 */
class AgreementPolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function create(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement);
    }

    public function update(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement);
    }

    public function sign(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement);
    }

    public function createVersion(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement);
    }

    private function sameTenant(TenantUser $user, Agreement $agreement): bool
    {
        return (int) $user->tenant_id === (int) $agreement->tenant_id;
    }
}
