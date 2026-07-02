<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Events\UpgradeRequested;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\UpgradeRequest;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\SuperAdmin\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Tenant billing portal (tenant-admin only) + super-admin manual plan assignment
 * and offline payments. No Stripe (Session C). Real HTTP stack.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class BillingTest extends TestCase
{
    use DatabaseTransactions;

    private function makePlan(string $name, array $limits = [], array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'price_monthly' => 5000,
            'price_annual' => 50000,
            'is_active' => true,
            'is_free' => false,
            'trial_days' => 14,
            'modules' => ['fleet', 'invoice'],
            'limits' => $limits,
            'sort_order' => 0,
        ], $attrs));
    }

    private function makeTenant(string $slug, ?Plan $plan = null, string $status = Tenant::STATUS_ACTIVE): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => $status,
            'plan_id' => $plan?->id,
        ]);
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, string $status = Subscription::STATUS_ACTIVE): Subscription
    {
        app()->instance('current_tenant', $tenant);

        return Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => $status,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
            'trial_ends_at' => $status === Subscription::STATUS_TRIALING ? now()->addDays(10) : null,
        ]);
    }

    private function makeUser(Tenant $tenant, string $role, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'secret123',
            'role' => $role,
        ]);
    }

    private function makeSuperAdmin(): SuperAdmin
    {
        Role::firstOrCreate(['name' => SuperAdmin::ROLE_PLATFORM_OWNER, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret1234',
            'role' => SuperAdmin::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);
        $admin->assignRole(SuperAdmin::ROLE_PLATFORM_OWNER);

        return $admin;
    }

    public function test_tenant_admin_can_view_billing(): void
    {
        $plan = $this->makePlan('Starter', ['max_vehicles' => 10, 'max_staff_users' => 5, 'max_customers' => 100]);
        $tenant = $this->makeTenant('shop-a', $plan);
        $this->makeSubscription($tenant, $plan);
        $admin = $this->makeUser($tenant, TenantUser::ROLE_ADMIN, 'admin@shop.test');

        $this->actingAs($admin, 'tenant')
            ->get('/app/shop-a/billing')
            ->assertOk();
    }

    public function test_non_admin_staff_cannot_view_billing(): void
    {
        $plan = $this->makePlan('Starter');
        $tenant = $this->makeTenant('shop-b', $plan);
        $this->makeSubscription($tenant, $plan);
        $staff = $this->makeUser($tenant, TenantUser::ROLE_STAFF, 'staff@shop.test');

        $this->actingAs($staff, 'tenant')
            ->get('/app/shop-b/billing')
            ->assertForbidden();
    }

    public function test_tenant_admin_can_submit_an_upgrade_request(): void
    {
        Event::fake([UpgradeRequested::class]);

        $current = $this->makePlan('Starter');
        $target = $this->makePlan('Pro');
        $tenant = $this->makeTenant('shop-c', $current);
        $this->makeSubscription($tenant, $current);
        $admin = $this->makeUser($tenant, TenantUser::ROLE_ADMIN, 'admin@shopc.test');

        $this->actingAs($admin, 'tenant')
            ->post('/app/shop-c/billing/upgrade-request', [
                'requested_plan_id' => $target->id,
                'notes' => 'Need more vehicles',
            ])
            ->assertRedirect();

        app()->instance('current_tenant', $tenant);
        $req = UpgradeRequest::withoutGlobalScope(\App\Scopes\TenantScope::class)
            ->where('tenant_id', $tenant->id)->first();

        $this->assertNotNull($req);
        $this->assertSame($target->id, $req->requested_plan_id);
        $this->assertSame($current->id, $req->current_plan_id);
        $this->assertSame(UpgradeRequest::STATUS_PENDING, $req->status);
        Event::assertDispatched(UpgradeRequested::class);
    }

    public function test_super_admin_assign_plan_swaps_subscription_and_ends_trial(): void
    {
        Event::fake([SubscriptionUpgraded::class]);

        $trialPlan = $this->makePlan('Trial', [], ['is_free' => true]);
        $paidPlan = $this->makePlan('Pro');
        $tenant = $this->makeTenant('shop-d', $trialPlan, Tenant::STATUS_TRIAL);
        $tenant->update(['trial_ends_at' => now()->addDays(5)]);
        $oldSub = $this->makeSubscription($tenant, $trialPlan, Subscription::STATUS_TRIALING);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'superadmin')
            ->post("/superadmin/tenants/{$tenant->slug}/assign-plan", [
                'plan_id' => $paidPlan->id,
                'billing_cycle' => 'annual',
            ])
            ->assertRedirect();

        $tenant->refresh();
        $this->assertSame($paidPlan->id, $tenant->plan_id);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertNull($tenant->trial_ends_at);

        // Old subscription cancelled; a new active annual subscription exists.
        $this->assertSame(Subscription::STATUS_CANCELLED, $oldSub->fresh()->status);
        app()->instance('current_tenant', $tenant);
        $active = $tenant->activeSubscription;
        $this->assertNotNull($active);
        $this->assertSame($paidPlan->id, $active->plan_id);
        $this->assertSame('annual', $active->billing_cycle);
        Event::assertDispatched(SubscriptionUpgraded::class);
    }

    public function test_super_admin_can_record_an_offline_payment(): void
    {
        $plan = $this->makePlan('Pro');
        $tenant = $this->makeTenant('shop-e', $plan);
        $sub = $this->makeSubscription($tenant, $plan);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'superadmin')
            ->post("/superadmin/tenants/{$tenant->slug}/offline-payment", [
                'amount' => 12500, // cents
                'method' => SubscriptionPayment::METHOD_BANK_TRANSFER,
                'reference' => 'TXN-999',
                'notes' => 'Q1 invoice',
                'paid_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        app()->instance('current_tenant', $tenant);
        $payment = SubscriptionPayment::where('subscription_id', $sub->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(12500, $payment->amount);
        $this->assertSame($admin->id, $payment->recorded_by);
    }

    public function test_super_admin_can_mark_upgrade_request_contacted(): void
    {
        $current = $this->makePlan('Starter');
        $target = $this->makePlan('Pro');
        $tenant = $this->makeTenant('shop-f', $current);
        app()->instance('current_tenant', $tenant);
        $req = UpgradeRequest::create([
            'tenant_id' => $tenant->id,
            'requested_plan_id' => $target->id,
            'current_plan_id' => $current->id,
            'status' => UpgradeRequest::STATUS_PENDING,
        ]);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'superadmin')
            ->post("/superadmin/upgrade-requests/{$req->id}/contacted")
            ->assertRedirect();

        $this->assertSame(UpgradeRequest::STATUS_CONTACTED, $req->fresh()->status);
    }
}
