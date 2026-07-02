<?php

namespace App\Modules\SaasCore\Events;

use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a super admin assigns a plan to a tenant, creating a new active
 * subscription (TenantManagementController@assignPlan). CLAUDE.md-required event.
 *
 * Carries the tenant and the NEW subscription. No listeners yet (upgrade
 * confirmation notices are a later Notification session).
 */
class SubscriptionUpgraded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Tenant $tenant,
        public readonly Subscription $subscription,
    ) {}
}
