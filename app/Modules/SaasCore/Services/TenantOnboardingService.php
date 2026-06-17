<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\DTOs\TenantOnboardingDTO;
use App\Modules\SaasCore\Events\TenantRegistered;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Onboards a new tenant: creates the Tenant, attaches a Plan, and opens a
 * trialing Subscription — all in one transaction.
 *
 * Side effects (welcome email, provisioning) are NOT performed here; they
 * belong to listeners on the TenantRegistered event.
 */
class TenantOnboardingService extends BaseService
{
    public function execute(TenantOnboardingDTO $dto): Tenant
    {
        return DB::transaction(function () use ($dto): Tenant {
            $plan = $this->resolvePlan($dto->plan_id);

            $now = now();
            $trialEndsAt = $now->copy()->addDays($plan->trial_days);

            $tenant = Tenant::create([
                'name' => $dto->name,
                'status' => Tenant::STATUS_TRIAL,
                'plan_id' => $plan->id,
                'trial_ends_at' => $trialEndsAt,
            ]);

            // Subscription uses HasTenant; tenant_id is set explicitly because
            // the new tenant is not the bound request context during onboarding.
            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => Subscription::STATUS_TRIALING,
                'billing_cycle' => Subscription::BILLING_MONTHLY,
                'current_period_start' => $now,
                'current_period_end' => $trialEndsAt,
                'trial_ends_at' => $trialEndsAt,
            ]);

            TenantRegistered::dispatch($tenant);

            return $tenant;
        });
    }

    /**
     * Resolve the plan to onboard onto: the explicitly selected plan, else the
     * default free plan, else the first active plan by sort order.
     */
    private function resolvePlan(?int $planId): Plan
    {
        if ($planId !== null) {
            return Plan::where('is_active', true)->findOrFail($planId);
        }

        $plan = Plan::where('is_active', true)
            ->orderByDesc('is_free')
            ->orderBy('sort_order')
            ->first();

        if ($plan === null) {
            throw new RuntimeException('No active plan available for onboarding.');
        }

        return $plan;
    }
}
