<?php

namespace App\Modules\SuperAdmin\Events;

use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a super admin rejects a tenant's registration that was awaiting
 * manual approval (TenantManagementController::reject()).
 *
 * Listened to by SendTenantRejectedNotification.
 */
class TenantRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}
}
