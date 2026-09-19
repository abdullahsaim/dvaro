<?php

namespace App\Modules\Agreement\Policies;

use App\Modules\Agreement\Models\AgreementTemplate;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Agreement terms templates (tenant side). Everyone in the company may READ
 * the wording; only tenant_admin may write it — terms are legal text, so the
 * edit right sits with the account owner (client decision).
 *
 * Platform defaults (tenant_id NULL) are READ-ONLY for every tenant: they can
 * be copied into the tenant's own library, never edited in place.
 */
class AgreementTemplatePolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, AgreementTemplate $template): bool
    {
        return $template->isPlatformDefault() || $this->owns($user, $template);
    }

    public function create(TenantUser $user): bool
    {
        return $this->isAdmin($user);
    }

    /** Platform defaults are never editable by a tenant — copy first. */
    public function update(TenantUser $user, AgreementTemplate $template): bool
    {
        return $this->isAdmin($user) && $this->owns($user, $template);
    }

    public function copy(TenantUser $user, AgreementTemplate $template): bool
    {
        return $this->isAdmin($user) && $template->isPlatformDefault();
    }

    private function owns(TenantUser $user, AgreementTemplate $template): bool
    {
        return $template->tenant_id !== null && (int) $template->tenant_id === (int) $user->tenant_id;
    }

    private function isAdmin(TenantUser $user): bool
    {
        return $user->role === TenantUser::ROLE_ADMIN;
    }
}
