<?php

namespace App\Modules\Fleet\Policies;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for Fleet vehicle actions.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: route-model binding
 * for {vehicle} resolves through the global scope, so a vehicle belonging to
 * another tenant is never found (404) before a policy ever runs. This policy is
 * explicit defense-in-depth — it asserts the acting tenant user and the target
 * vehicle belong to the same tenant.
 *
 * IMPORTANT: these checks must be evaluated against the TENANT guard user. The
 * controller calls gate()->forUser(auth('tenant')->user())->authorize(...) for
 * exactly this reason — see FleetController.
 */
class VehiclePolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function create(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($user, $vehicle);
    }

    public function update(TenantUser $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($user, $vehicle);
    }

    public function delete(TenantUser $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($user, $vehicle);
    }

    public function changeStatus(TenantUser $user, Vehicle $vehicle): bool
    {
        return $this->sameTenant($user, $vehicle);
    }

    private function sameTenant(TenantUser $user, Vehicle $vehicle): bool
    {
        return (int) $user->tenant_id === (int) $vehicle->tenant_id;
    }
}
