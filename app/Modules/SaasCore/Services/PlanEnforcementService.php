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
     * Assert a single upload of $bytes is within the plan's max_file_size_mb.
     * Absent / negative limit = unlimited (same convention as check()).
     *
     * @throws PlanLimitExceededException when the file is larger than allowed
     */
    public function checkFileSize(int $bytes): void
    {
        $limitMb = $this->fileSizeLimitMb();

        if ($limitMb === null) {
            return;
        }

        if ($bytes > $limitMb * 1024 * 1024) {
            throw new PlanLimitExceededException(
                'max_file_size_mb',
                $limitMb,
                (int) ceil($bytes / 1024 / 1024),
                __('common.plan.file_too_large', ['limit' => $limitMb]),
            );
        }
    }

    /**
     * The plan's per-upload file size limit in MB, or null when unlimited or
     * no plan is resolved.
     */
    public function fileSizeLimitMb(): ?int
    {
        $limit = $this->resolveCurrentPlan()?->getLimit('max_file_size_mb') ?? -1;

        return $limit < 0 ? null : $limit;
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
