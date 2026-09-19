<?php

namespace Tests\Feature;

use App\Exceptions\PlanLimitExceededException;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\UsageService;
use App\Modules\Workshop\Models\Mechanic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * max_mechanics plan limit — hard block on mechanic creation, billing usage
 * meter, and the Inertia-friendly PlanLimitExceededException rendering.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class MechanicPlanLimitTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $limits): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug,
            'slug' => 'plan-'.$slug.'-'.uniqid(),
            'price_monthly' => 5000,
            'price_annual' => 50000,
            'is_active' => true,
            'is_free' => false,
            'trial_days' => 14,
            'modules' => Plan::MODULE_KEYS,
            'limits' => $limits,
            'sort_order' => 0,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
        ]);

        app()->instance('current_tenant', $tenant);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $tenant;
    }

    private function makeAdmin(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin',
            'email' => 'admin@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    private function makeMechanic(Tenant $tenant, string $email, array $attrs = []): Mechanic
    {
        app()->instance('current_tenant', $tenant);

        return Mechanic::create(array_merge([
            'name' => 'Mech',
            'email' => $email,
            'password' => 'secret123',
            'is_active' => true,
        ], $attrs));
    }

    private function payload(string $email): array
    {
        return ['name' => 'New Mech', 'email' => $email, 'password' => 'secret123', 'is_active' => true];
    }

    public function test_max_mechanics_is_a_plan_limit_key(): void
    {
        $this->assertContains('max_mechanics', Plan::LIMIT_KEYS);
    }

    public function test_creation_is_hard_blocked_at_the_limit_with_an_inline_error(): void
    {
        $t = $this->makeTenant('mlim-a', ['max_mechanics' => 2]);
        $admin = $this->makeAdmin($t);
        $this->makeMechanic($t, 'one@mlim.test');
        $this->makeMechanic($t, 'two@mlim.test');

        $this->actingAs($admin, 'tenant')
            ->from('/app/mlim-a/mechanics/create')
            ->post('/app/mlim-a/mechanics', $this->payload('three@mlim.test'))
            ->assertRedirect('/app/mlim-a/mechanics/create')
            ->assertSessionHasErrors('plan_limit')
            ->assertSessionHas('error');

        app()->instance('current_tenant', $t);
        $this->assertSame(2, Mechanic::count());
    }

    public function test_json_callers_still_get_the_403_payload(): void
    {
        $t = $this->makeTenant('mlim-b', ['max_mechanics' => 1]);
        $admin = $this->makeAdmin($t);
        $this->makeMechanic($t, 'one@mlimb.test');

        $this->actingAs($admin, 'tenant')
            ->postJson('/app/mlim-b/mechanics', $this->payload('two@mlimb.test'))
            ->assertForbidden()
            ->assertJson(['code' => 'PLAN_LIMIT_REACHED', 'upgrade_required' => true]);
    }

    public function test_below_limit_creates_and_unset_limit_is_unlimited(): void
    {
        $t = $this->makeTenant('mlim-c', ['max_mechanics' => 2]);
        $admin = $this->makeAdmin($t);

        $this->actingAs($admin, 'tenant')
            ->post('/app/mlim-c/mechanics', $this->payload('ok@mlimc.test'))
            ->assertSessionHasNoErrors();

        $u = $this->makeTenant('mlim-d', []);
        $adminU = $this->makeAdmin($u);
        foreach (range(1, 3) as $i) {
            $this->makeMechanic($u, "m{$i}@mlimd.test");
        }

        $this->actingAs($adminU, 'tenant')
            ->post('/app/mlim-d/mechanics', $this->payload('four@mlimd.test'))
            ->assertSessionHasNoErrors();

        app()->instance('current_tenant', $u);
        $this->assertSame(4, Mechanic::count());
    }

    public function test_soft_deleted_do_not_count_but_deactivated_do(): void
    {
        $t = $this->makeTenant('mlim-e', ['max_mechanics' => 2]);
        $admin = $this->makeAdmin($t);
        $this->makeMechanic($t, 'gone@mlime.test')->delete();
        $this->makeMechanic($t, 'idle@mlime.test', ['is_active' => false]);

        // 1 counted (deactivated) → one more allowed.
        $this->actingAs($admin, 'tenant')
            ->post('/app/mlim-e/mechanics', $this->payload('new@mlime.test'))
            ->assertSessionHasNoErrors();

        // Now 2 counted → blocked.
        $this->actingAs($admin, 'tenant')
            ->post('/app/mlim-e/mechanics', $this->payload('over@mlime.test'))
            ->assertSessionHasErrors('plan_limit');
    }

    public function test_usage_meter_reports_mechanics(): void
    {
        $t = $this->makeTenant('mlim-f', ['max_mechanics' => 4]);
        $this->makeMechanic($t, 'a@mlimf.test');
        $this->makeMechanic($t, 'b@mlimf.test');

        app()->instance('current_tenant', $t);
        $usage = app(UsageService::class)->getUsage($t);

        $this->assertSame(['current' => 2, 'limit' => 4, 'percentage' => 50, 'approaching' => false], $usage['mechanics']);
    }
}
