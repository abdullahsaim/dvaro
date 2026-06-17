<?php

namespace App\Modules\SaasCore\Services;

use App\Exceptions\PlanLimitExceededException;
use App\Modules\SaasCore\Models\Plan;
use App\Services\BaseService;

/**
 * Enforces a tenant's plan limits as HARD BLOCKS.
 *
 * Limits are checked before a create action. Any limit value < 0 means
 * UNLIMITED (including an absent key, which Plan::getLimit() reports as -1),
 * so an incomplete plan config never hard-blocks every action.
 */
class PlanEnforcementService extends BaseService
{
    /**
     * Assert that creating one more of $limitKey is permitted.
     *
     * @param  string  $limitKey      e.g. 'max_vehicles', 'max_staff'
     * @param  int     $currentCount  how many the tenant already has
     *
     * @throws PlanLimitExceededException when the limit would be breached
     */
    public function check(string $limitKey, int $currentCount): void
    {
        $plan = $this->resolveCurrentPlan();

        // No plan resolved (e.g. mid-onboarding) — nothing to enforce.
        if ($plan === null) {
            return;
        }

        $limit = $plan->getLimit($limitKey);

        // Negative limit (incl. absent key => -1) means unlimited: skip.
        if ($limit < 0) {
            return;
        }

        if ($currentCount >= $limit) {
            throw new PlanLimitExceededException($limitKey, $limit, $currentCount);
        }
    }

    /**
     * The active plan of the tenant bound to the current request context.
     */
    private function resolveCurrentPlan(): ?Plan
    {
        if (! app()->bound('current_tenant')) {
            return null;
        }

        return app('current_tenant')->activePlan();
    }
}
