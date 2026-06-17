<?php

namespace App\Modules\SaasCore\Events;

use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once a tenant has been onboarded (record + plan + trialing
 * subscription created). Listeners handle side effects such as welcome
 * email / provisioning — none of which live in the onboarding service.
 */
class TenantRegistered
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
    ) {}
}
