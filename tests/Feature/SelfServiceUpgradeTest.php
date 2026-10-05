<?php

namespace Tests\Feature;

use App\Contracts\PaymentProviderInterface;
use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Providers\StripePaymentProvider;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Self-service, in-place plan change on an EXISTING Stripe subscription —
 * immediate, with a prorated charge right now (UpgradeSubscriptionAction).
 * The Stripe SDK is never hit: StripePaymentProvider is mocked, same pattern
 * as StripeBillingTest.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class SelfServiceUpgradeTest extends TestCase
{
    use DatabaseTransactions;

    private function mockStripe(): MockInterface
    {
        $mock = Mockery::mock(StripePaymentProvider::class);

        $this->instance(StripePaymentProvider::class, $mock);
        $this->instance(PaymentProviderInterface::class, $mock);

        return $mock;
    }

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plan '.uniqid(), 'slug' => 'plan-'.uniqid(),
            'price_monthly' => 5000, 'price_annual' => 50000, 'is_active' => true, 'is_free' => false,
            'trial_days' => 14, 'modules' => [], 'limits' => [], 'sort_order' => 0,
            'stripe_product_id' => 'prod_'.uniqid(),
            'stripe_monthly_price_id' => 'price_m_'.uniqid(),
            'stripe_annual_price_id' => 'price_a_'.uniqid(),
        ], $attrs));
    }

    private function makeTenant(string $slug, Plan $plan): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE, 'plan_id' => $plan->id,
        ]);
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, array $attrs = []): Subscription
    {
        app()->instance('current_tenant', $tenant);

        return Subscription::create(array_merge([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE, 'billing_cycle' => Subscription::BILLING_MONTHLY,
            'gateway' => Subscription::GATEWAY_STRIPE, 'gateway_subscription_id' => 'sub_'.uniqid(),
            'stripe_price_id' => $plan->stripe_monthly_price_id,
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ], $attrs));
    }

    private function makeAdmin(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@test.au',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    public function test_an_admin_can_upgrade_their_active_stripe_subscription_immediately(): void
    {
        $oldPlan = $this->makePlan(['name' => 'Starter', 'price_monthly' => 5000]);
        $newPlan = $this->makePlan(['name' => 'Growth', 'price_monthly' => 15000]);
        $tenant = $this->makeTenant('upgrade-now', $oldPlan);
        $subscription = $this->makeSubscription($tenant, $oldPlan);
        $admin = $this->makeAdmin($tenant);

        Event::fake([SubscriptionUpgraded::class]);

        $newPeriodStart = now();
        $newPeriodEnd = now()->addMonth();

        $this->mockStripe()
            ->shouldReceive('changeSubscriptionPlan')
            ->once()
            ->with($subscription->gateway_subscription_id, $newPlan->stripe_monthly_price_id)
            ->andReturn([
                'current_period_start' => $newPeriodStart,
                'current_period_end' => $newPeriodEnd,
                'amount_charged' => 10000, // the prorated difference, in cents
                'gateway_price_id' => $newPlan->stripe_monthly_price_id,
            ]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$tenant->slug}/billing/upgrade/{$newPlan->id}")
            ->assertRedirect("/app/{$tenant->slug}/billing");

        $fresh = $subscription->fresh();
        $this->assertSame($newPlan->id, $fresh->plan_id);
        $this->assertSame($newPlan->stripe_monthly_price_id, $fresh->stripe_price_id);
        $this->assertSame($newPlan->id, $tenant->fresh()->plan_id);

        $payment = SubscriptionPayment::where('subscription_id', $subscription->id)->firstOrFail();
        $this->assertSame(10000, $payment->amount);
        $this->assertSame(SubscriptionPayment::METHOD_STRIPE, $payment->method);

        Event::assertDispatched(SubscriptionUpgraded::class);

        $log = PlatformActivityLog::where('action', 'tenant.self_service_plan_upgrade')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($newPlan->id, $log->new_values['plan_id']);
    }

    public function test_a_zero_dollar_change_writes_no_payment_row(): void
    {
        $oldPlan = $this->makePlan(['name' => 'A']);
        $newPlan = $this->makePlan(['name' => 'B']);
        $tenant = $this->makeTenant('upgrade-zero', $oldPlan);
        $subscription = $this->makeSubscription($tenant, $oldPlan);
        $admin = $this->makeAdmin($tenant);

        $this->mockStripe()->shouldReceive('changeSubscriptionPlan')->once()->andReturn([
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
            'amount_charged' => 0, 'gateway_price_id' => $newPlan->stripe_monthly_price_id,
        ]);

        $this->actingAs($admin, 'tenant')->post("/app/{$tenant->slug}/billing/upgrade/{$newPlan->id}");

        $this->assertSame(0, SubscriptionPayment::where('subscription_id', $subscription->id)->count());
        $this->assertSame($newPlan->id, $subscription->fresh()->plan_id);
    }

    public function test_a_tenant_with_no_gateway_subscription_cannot_self_service_upgrade(): void
    {
        $plan = $this->makePlan();
        $newPlan = $this->makePlan();
        $tenant = $this->makeTenant('upgrade-none', $plan);
        $admin = $this->makeAdmin($tenant);
        // No Subscription row at all — e.g. still on DVARO's own free trial.

        $this->mockStripe()->shouldNotReceive('changeSubscriptionPlan');

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$tenant->slug}/billing/upgrade/{$newPlan->id}")
            ->assertSessionHas('error');
    }

    public function test_staff_cannot_self_service_upgrade(): void
    {
        $plan = $this->makePlan();
        $newPlan = $this->makePlan();
        $tenant = $this->makeTenant('upgrade-staff', $plan);
        $this->makeSubscription($tenant, $plan);

        app()->instance('current_tenant', $tenant);
        $staff = TenantUser::create([
            'name' => 'Staffer', 'email' => 'staff-'.uniqid().'@test.au',
            'password' => 'secret123', 'role' => TenantUser::ROLE_STAFF, 'is_active' => true,
        ]);

        $this->mockStripe()->shouldNotReceive('changeSubscriptionPlan');

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$tenant->slug}/billing/upgrade/{$newPlan->id}")
            ->assertForbidden();
    }

    public function test_a_gateway_failure_leaves_the_local_subscription_untouched(): void
    {
        $oldPlan = $this->makePlan();
        $newPlan = $this->makePlan();
        $tenant = $this->makeTenant('upgrade-fail', $oldPlan);
        $subscription = $this->makeSubscription($tenant, $oldPlan);
        $admin = $this->makeAdmin($tenant);

        $this->mockStripe()
            ->shouldReceive('changeSubscriptionPlan')
            ->once()
            ->andThrow(new \RuntimeException('Stripe API unreachable'));

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$tenant->slug}/billing/upgrade/{$newPlan->id}")
            ->assertSessionHas('error');

        $this->assertSame($oldPlan->id, $subscription->fresh()->plan_id);
    }
}
