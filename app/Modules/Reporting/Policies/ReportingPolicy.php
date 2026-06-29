<?php

namespace App\Modules\Reporting\Policies;

use App\Modules\Reporting\Models\ReportExport;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Authorization for Reporting actions.
 *
 * Tenant isolation is PRIMARILY enforced by TenantScope: route-model binding for
 * {export} resolves through the global scope, so an export belonging to another
 * tenant is never found (404) before this policy runs. This policy is explicit
 * defense-in-depth — it asserts the acting tenant user and the target export
 * belong to the same tenant.
 *
 * IMPORTANT: evaluated against the TENANT guard user. The controller calls
 * Gate::forUser(auth('tenant')->user())->authorize(...) — see ReportingController.
 */
class ReportingPolicy
{
    /** View any report (the whole reporting area). */
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    /** Queue an export. */
    public function export(TenantUser $user): bool
    {
        return true;
    }

    /** Download a generated export file (own tenant only). */
    public function download(TenantUser $user, ReportExport $export): bool
    {
        return (int) $user->tenant_id === (int) $export->tenant_id;
    }
}
