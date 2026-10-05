<?php

namespace App\Modules\SuperAdmin\Events;

use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a super admin approves a tenant that registered while
 * manual_tenant_approval was on (TenantManagementController::approve()).
 *
 * Listened to by SendTenantApprovedNotification — tells the admin they can
 * now log in.
 */
class TenantApproved
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}
}
