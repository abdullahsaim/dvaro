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

    /** Email/WhatsApp the customer a link to review and sign remotely. */
    public function sendForSigning(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement);
    }

    public function createVersion(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement);
    }

    /**
     * Record the vehicle return / inspection that closes out this rental.
     * Only a signed/active agreement can be returned — not a draft (nothing
     * to return) and not one already completed (DB-unique on agreement_id
     * backs this up, but the policy gives a clean 403 instead of a 500).
     */
    public function returnVehicle(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement)
            && in_array($agreement->status, [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE], true);
    }

    /**
     * Re-queue a MISSING agreement PDF.
     *
     * Deliberately not a general "regenerate": an agreement PDF is written once
     * at signing and kept, so a company that rebrands later must not be able to
     * re-render documents people have already signed. This only recovers a PDF
     * that is absent — the controller enforces that part.
     */
    public function rebuildPdf(TenantUser $user, Agreement $agreement): bool
    {
        return $this->sameTenant($user, $agreement)
            && in_array($user->role, [TenantUser::ROLE_ADMIN, TenantUser::ROLE_ACCOUNTS], true);
    }

    private function sameTenant(TenantUser $user, Agreement $agreement): bool
    {
        return (int) $user->tenant_id === (int) $agreement->tenant_id;
    }
}
