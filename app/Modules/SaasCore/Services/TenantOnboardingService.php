<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\SaasCore\DTOs\TenantOnboardingDTO;
use App\Modules\SaasCore\Events\TenantRegistered;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Onboards a new tenant: creates the Tenant, attaches a Plan, opens a trialing
 * Subscription, and provisions the first admin TenantUser — all in ONE
 * transaction. If any step (including the admin user / role assignment) throws,
 * the whole onboarding rolls back: no orphan tenant, subscription, or user.
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

            // First admin login for the tenant. tenant_id is set explicitly
            // (no bound current_tenant during onboarding); an INSERT does not
            // trigger TenantScope, so this is safe. The Spatie role must already
            // exist under guard 'tenant' (TenantRolesSeeder) — assignRole()
            // throwing here rolls the whole transaction back.
            $user = TenantUser::create([
                'tenant_id' => $tenant->id,
                // No separate user-name field is collected at signup yet; the
                // company name doubles as the first admin's display name.
                'name' => $dto->name,
                'email' => $dto->email,
                'password' => $dto->password, // hashed via model cast
                'role' => TenantUser::ROLE_ADMIN,
            ]);

            $user->assignRole(TenantUser::ROLE_ADMIN);

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
