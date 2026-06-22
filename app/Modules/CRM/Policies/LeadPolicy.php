<?php

namespace App\Modules\CRM\Policies;

use App\Modules\CRM\Models\Lead;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for Lead actions (tenant app side only — the public intake form
 * is unauthenticated and guarded by the signed URL, not this policy).
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: {lead} route-model
 * binding resolves through the global scope, so another tenant's lead is never
 * found (404) before a policy runs. This is explicit defense-in-depth.
 *
 * IMPORTANT: evaluate against the TENANT guard user. LeadController calls
 * Gate::forUser(auth('tenant')->user())->authorize(...) for exactly this reason.
 */
class LeadPolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function create(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, Lead $lead): bool
    {
        return $this->sameTenant($user, $lead);
    }

    public function update(TenantUser $user, Lead $lead): bool
    {
        return $this->sameTenant($user, $lead);
    }

    public function delete(TenantUser $user, Lead $lead): bool
    {
        return $this->sameTenant($user, $lead);
    }

    public function convert(TenantUser $user, Lead $lead): bool
    {
        return $this->sameTenant($user, $lead);
    }

    public function expire(TenantUser $user, Lead $lead): bool
    {
        return $this->sameTenant($user, $lead);
    }

    private function sameTenant(TenantUser $user, Lead $lead): bool
    {
        return (int) $user->tenant_id === (int) $lead->tenant_id;
    }
}
