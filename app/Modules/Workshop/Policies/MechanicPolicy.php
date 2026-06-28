<?php

namespace App\Modules\Workshop\Policies;

use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for service-log actions.
 *
 * Two guards touch service logs: the MECHANIC guard (the portal — create/update/
 * addPart) and the TENANT guard (the admin workshop view — read-only). The READ
 * abilities (viewAny/view) therefore accept any Authenticatable; both the
 * Mechanic and TenantUser models carry tenant_id, which is all the same-tenant
 * check needs. The MUTATING abilities are mechanic-only (typed Mechanic) and
 * additionally enforce ownership: a mechanic may only mutate THEIR OWN logs,
 * unless they hold the senior role.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: route-model binding
 * for {log} resolves through the global scope, so a cross-tenant log is never
 * found (404) before a policy ever runs. This policy is defense-in-depth.
 *
 * IMPORTANT: mechanic actions call Gate::forUser(auth('mechanic')->user());
 * the admin view calls Gate::forUser(auth('tenant')->user()). Never $this->
 * authorize() (resolves the empty web guard). See the controllers.
 */
class MechanicPolicy
{
    public function viewAny(Authenticatable $user): bool
    {
        return true;
    }

    public function create(Mechanic $mechanic): bool
    {
        return true;
    }

    public function view(Authenticatable $user, ServiceLog $log): bool
    {
        return (int) $user->tenant_id === (int) $log->tenant_id;
    }

    public function update(Mechanic $mechanic, ServiceLog $log): bool
    {
        if (! $this->sameTenant($mechanic, $log)) {
            return false;
        }

        // Own log, or any tenant log if senior.
        return (int) $log->mechanic_id === (int) $mechanic->id
            || $mechanic->hasRole(Mechanic::ROLE_SENIOR);
    }

    public function addPart(Mechanic $mechanic, ServiceLog $log): bool
    {
        return $this->update($mechanic, $log);
    }

    private function sameTenant(Mechanic $mechanic, ServiceLog $log): bool
    {
        return (int) $mechanic->tenant_id === (int) $log->tenant_id;
    }
}
