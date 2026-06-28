<?php

namespace App\Modules\SuperAdmin\Events;

use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a super admin re-activates a suspended tenant
 * (TenantManagementController).
 *
 * No listeners yet (tenant-activation notifications are a later session).
 */
class TenantActivated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}
}
