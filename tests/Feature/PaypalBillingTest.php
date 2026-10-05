<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Events\SubscriptionCancelled;
use App\Modules\SaasCore\Events\SubscriptionPaymentFailed;
use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Providers\PaypalPaymentProvider;
use App\Scopes\TenantScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * PayPal subscription billing — checkout initiation, webhook-driven
 * activation (idempotent), payment failure, cancellation. The PayPal REST
 * API is never hit: PaypalPaymentProvider is mocked over its own concrete
 * class (every PayPal checkout/webhook/cancel class depends on it directly,
 * not PaymentProviderInterface — that interface stays flat-bound to Stripe).
 * Mirrors StripeBillingTest exactly, PayPal event shapes substituted in.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class PaypalBillingTest extends TestCase
{
    use DatabaseTransactions;

    private function mockPaypal(): MockInterface
    {
        $mock = Mockery::mock(PaypalPaymentProvider::class);

        $this->instance(PaypalPaymentProvider::class, $mock);

        return $mock;
    }

    private function makePlan(array $attrs = []): Plan
    {
        return Plan::create(array_merge([
            'name' => 'Plan '.uniqid(),
            'slug' => 'plan-'.uniqid(),
            'price_monthly' => 5000,
            'price_annual' => 50000,
            'is_active' => true,
            'is_free' => false,
            'trial_days' => 14,
            'modules' => ['fleet', 'invoice'],
            'limits' => [],
            'sort_order' => 0,
            'paypal_product_id' => 'prod_'.uniqid(),
            'paypal_monthly_plan_id' => 'plan_m_'.uniqid(),
            'paypal_annual_plan_id' => 'plan_a_'.uniqid(),
        ], $attrs));
    }

    private function makeTenant(string $slug, ?Plan $plan = null, array $attrs = []): Tenant
    {
        return Tenant::create(array_merge([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan?->id,
        ], $attrs));
    }

    private function makeSubscription(Tenant $tenant, Plan $plan, array $attrs = []): Subscription
    {
        app()->instance('current_tenant', $tenant);

        return Subscription::create(array_merge([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ], $attrs));
    }

    private function makeAdmin(Tenant $tenant, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    /**
     * A PayPal webhook event as the REST API would decode it.
     */
    private function paypalEvent(string $type, array $resource): object
    {
        return json_decode(json_encode([
            'id' => 'WH-'.uniqid(),
            'event_type' => $type,
            'resource' => $resource,
        ]));
    }

    // ------------------------------------------------------------------
    // Checkout initiation
    // ------------------------------------------------------------------

    public function test_non_admin_staff_cannot_start_paypal_checkout(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('paypal-a', $plan);
        $this->makeSubscription($tenant, $plan);

        app()->instance('current_tenant', $tenant);
        $staff = TenantUser::create([
            'name' => 'Staff',
            'email' => 'staff@paypal-a.test',
            'password' => 'secret123',
            'role' => TenantUser::ROLE_STAFF,
        ]);

        $this->actingAs($staff, 'tenant')
            ->post("/app/paypal-a/billing/paypal/checkout/{$plan->id}", ['billing_cycle' => 'monthly'])
            ->assertForbidden();
    }

    public function test_admin_checkout_redirects_to_paypal_approval_page(): void
    {
        $current = $this->makePlan();
        $target = $this->makePlan();
        $tenant = $this->makeTenant('paypal-b', $current);
        $this->makeSubscription($tenant, $current);
        $admin = $this->makeAdmin($tenant, 'admin@paypal-b.test');

        $this->mockPaypal()
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(function (Tenant $t, Plan $p, string $cycle, string $successUrl, string $cancelUrl) use ($tenant, $target) {
                return $t->id === $tenant->id
                    && $p->id === $target->id
                    && $cycle === 'annual'
                    && str_contains($successUrl, '/app/paypal-b/billing/paypal/success')
                    && str_contains($cancelUrl, '/app/paypal-b/billing/paypal/cancel');
            })
            ->andReturn('https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=test');

        $this->actingAs($admin, 'tenant')
            ->post("/app/paypal-b/billing/paypal/checkout/{$target->id}", ['billing_cycle' => 'annual'])
            ->assertRedirect('https://www.sandbox.paypal.com/webapps/billing/subscriptions?ba_token=test');
    }

    public function test_checkout_blocked_when_plan_not_synced_to_paypal(): void
    {
        $plan = $this->makePlan([
            'paypal_product_id' => null,
            'paypal_monthly_plan_id' => null,
            'paypal_annual_plan_id' => null,
        ]);
        $tenant = $this->makeTenant('paypal-c');
        $admin = $this->makeAdmin($tenant, 'admin@paypal-c.test');

        $this->mockPaypal()->shouldNotReceive('createCheckoutSession');

        $this->actingAs($admin, 'tenant')
            ->from('/app/paypal-c/billing')
            ->post("/app/paypal-c/billing/paypal/checkout/{$plan->id}", ['billing_cycle' => 'monthly'])
            ->assertRedirect('/app/paypal-c/billing')
            ->assertSessionHas('error');
    }

    // ------------------------------------------------------------------
    // Webhook — signature + activation
    // ------------------------------------------------------------------

    public function test_paypal_webhook_with_invalid_signature_is_rejected_400(): void
    {
        $this->mockPaypal()
            ->shouldReceive('constructWebhookEvent')
            ->once()
            ->andThrow(new \RuntimeException('PayPal webhook signature verification failed.'));

        $this->postJson('/paypal/webhook', ['fake' => 'payload'])
            ->assertStatus(400);
    }

    public function test_subscription_activated_webhook_activates_the_subscription(): void
    {
        Event::fake([SubscriptionUpgraded::class]);

        $trialPlan = $this->makePlan(['is_free' => true]);
        $paidPlan = $this->makePlan();
        $tenant = $this->makeTenant('paypal-f', $trialPlan, ['status' => Tenant::STATUS_TRIAL]);
        $oldSub = $this->makeSubscription($tenant, $trialPlan, [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(5),
        ]);

        $event = $this->paypalEvent('BILLING.SUBSCRIPTION.ACTIVATED', [
            'id' => 'I-paypal-f',
            'plan_id' => $paidPlan->paypal_monthly_plan_id,
            'custom_id' => (string) $tenant->id,
            'status' => 'ACTIVE',
        ]);

        $this->mockPaypal()->shouldReceive('constructWebhookEvent')->once()->andReturn($event);

        $this->postJson('/paypal/webhook', [])->assertOk();

        // Old subscription cancelled; the PayPal-billed one is active.
        $this->assertSame(Subscription::STATUS_CANCELLED, $oldSub->fresh()->status);

        $new = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', 'I-paypal-f')->first();
        $this->assertNotNull($new);
        $this->assertSame(Subscription::STATUS_ACTIVE, $new->status);
        $this->assertSame(Subscription::GATEWAY_PAYPAL, $new->gateway);
        $this->assertSame('active', $new->stripe_status);
        $this->assertSame('monthly', $new->billing_cycle);

        // Tenant promoted; trial over.
        $tenant->refresh();
        $this->assertSame($paidPlan->id, $tenant->plan_id);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertNull($tenant->trial_ends_at);

        // The subscription payment was recorded.
        $payment = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('subscription_id', $new->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(5000, $payment->amount);
        $this->assertSame(SubscriptionPayment::METHOD_PAYPAL, $payment->method);
        $this->assertSame('I-paypal-f', $payment->reference);

        Event::assertDispatched(SubscriptionUpgraded::class);
    }

    public function test_subscription_activated_webhook_is_idempotent_across_retries(): void
    {
        $paidPlan = $this->makePlan();
        $tenant = $this->makeTenant('paypal-g');

        $event = $this->paypalEvent('BILLING.SUBSCRIPTION.ACTIVATED', [
            'id' => 'I-paypal-g',
            'plan_id' => $paidPlan->paypal_annual_plan_id,
            'custom_id' => (string) $tenant->id,
        ]);

        $this->mockPaypal()->shouldReceive('constructWebhookEvent')->twice()->andReturn($event);

        $this->postJson('/paypal/webhook', [])->assertOk();
        $this->postJson('/paypal/webhook', [])->assertOk(); // PayPal retry

        $this->assertSame(1, Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', 'I-paypal-g')->count());
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)->count());
    }

    // ------------------------------------------------------------------
    // Webhook — lifecycle updates
    // ------------------------------------------------------------------

    public function test_paypal_payment_failed_webhook_marks_past_due_and_notifies_once(): void
    {
        Event::fake([SubscriptionPaymentFailed::class]);

        $plan = $this->makePlan();
        $tenant = $this->makeTenant('paypal-h', $plan);
        $sub = $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_PAYPAL,
            'gateway_subscription_id' => 'I-paypal-h',
            'stripe_status' => 'active',
        ]);

        $event = $this->paypalEvent('BILLING.SUBSCRIPTION.PAYMENT.FAILED', ['id' => 'I-paypal-h']);
        $this->mockPaypal()->shouldReceive('constructWebhookEvent')->twice()->andReturn($event);

        $this->postJson('/paypal/webhook', [])->assertOk();

        $sub->refresh();
        $this->assertSame('past_due', $sub->stripe_status);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $sub->status);

        // A second failure event while ALREADY past_due does not re-notify.
        $this->postJson('/paypal/webhook', [])->assertOk();
        Event::assertDispatchedTimes(SubscriptionPaymentFailed::class, 1);
    }

    public function test_paypal_subscription_cancelled_webhook_cancels_locally_and_demotes_tenant(): void
    {
        Event::fake([SubscriptionCancelled::class]);

        $plan = $this->makePlan();
        $tenant = $this->makeTenant('paypal-i', $plan, ['status' => Tenant::STATUS_ACTIVE]);
        $sub = $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_PAYPAL,
            'gateway_subscription_id' => 'I-paypal-i',
            'stripe_status' => 'active',
        ]);

        $event = $this->paypalEvent('BILLING.SUBSCRIPTION.CANCELLED', ['id' => 'I-paypal-i', 'status' => 'CANCELLED']);
        $this->mockPaypal()->shouldReceive('constructWebhookEvent')->once()->andReturn($event);

        $this->postJson('/paypal/webhook', [])->assertOk();

        $sub->refresh();
        $this->assertSame(Subscription::STATUS_CANCELLED, $sub->status);
        $this->assertNotNull($sub->cancelled_at);

        $this->assertSame(Tenant::STATUS_CANCELLED, $tenant->fresh()->status);
        Event::assertDispatched(SubscriptionCancelled::class);
    }

    // ------------------------------------------------------------------
    // Cancellation (tenant-initiated)
    // ------------------------------------------------------------------

    public function test_admin_cancel_dispatches_to_paypal_when_that_is_the_active_gateway(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('paypal-j', $plan);
        $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_PAYPAL,
            'gateway_subscription_id' => 'I-paypal-j',
            'stripe_status' => 'active',
        ]);
        $admin = $this->makeAdmin($tenant, 'admin@paypal-j.test');

        $this->mockPaypal()
            ->shouldReceive('cancelSubscription')
            ->once()
            ->with('I-paypal-j')
            ->andReturn(true);

        $this->actingAs($admin, 'tenant')
            ->post('/app/paypal-j/billing/cancel')
            ->assertRedirect()
            ->assertSessionHas('success');

        $updated = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', 'I-paypal-j')->first();
        $this->assertSame(Subscription::STRIPE_STATUS_CANCELING, $updated->stripe_status);
    }
}
