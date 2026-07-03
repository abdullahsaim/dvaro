<?php

namespace Tests\Feature;

use App\Contracts\PaymentProviderInterface;
use App\Modules\SaasCore\Events\SubscriptionCancelled;
use App\Modules\SaasCore\Events\SubscriptionPaymentFailed;
use App\Modules\SaasCore\Events\SubscriptionUpgraded;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Providers\StripePaymentProvider;
use App\Scopes\TenantScope;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Stripe Checkout subscription billing — checkout initiation, webhook-driven
 * activation (idempotent), payment failure, cancellation. The Stripe SDK is
 * never hit: StripePaymentProvider is mocked and bound over both the concrete
 * class (webhook controller) and PaymentProviderInterface (checkout/cancel).
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class StripeBillingTest extends TestCase
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
            'stripe_product_id' => 'prod_'.uniqid(),
            'stripe_monthly_price_id' => 'price_m_'.uniqid(),
            'stripe_annual_price_id' => 'price_a_'.uniqid(),
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
     * A Stripe event as the SDK would decode it (nested objects, not arrays).
     */
    private function stripeEvent(string $type, array $object): object
    {
        return json_decode(json_encode([
            'id' => 'evt_'.uniqid(),
            'type' => $type,
            'data' => ['object' => $object],
        ]));
    }

    // ------------------------------------------------------------------
    // Checkout initiation
    // ------------------------------------------------------------------

    public function test_non_admin_staff_cannot_start_checkout(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-a', $plan);
        $this->makeSubscription($tenant, $plan);

        app()->instance('current_tenant', $tenant);
        $staff = TenantUser::create([
            'name' => 'Staff',
            'email' => 'staff@stripe-a.test',
            'password' => 'secret123',
            'role' => TenantUser::ROLE_STAFF,
        ]);

        $this->actingAs($staff, 'tenant')
            ->post("/app/stripe-a/billing/checkout/{$plan->id}", ['billing_cycle' => 'monthly'])
            ->assertForbidden();
    }

    public function test_admin_checkout_redirects_to_stripe_hosted_page(): void
    {
        $current = $this->makePlan();
        $target = $this->makePlan();
        $tenant = $this->makeTenant('stripe-b', $current);
        $this->makeSubscription($tenant, $current);
        $admin = $this->makeAdmin($tenant, 'admin@stripe-b.test');

        $this->mockStripe()
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(function (Tenant $t, Plan $p, string $cycle, string $successUrl, string $cancelUrl) use ($tenant, $target) {
                return $t->id === $tenant->id
                    && $p->id === $target->id
                    && $cycle === 'annual'
                    && str_contains($successUrl, '/app/stripe-b/billing/checkout/success')
                    && str_contains($successUrl, '{CHECKOUT_SESSION_ID}')
                    && str_contains($cancelUrl, '/app/stripe-b/billing/checkout/cancel');
            })
            ->andReturn('https://checkout.stripe.com/c/pay/test-session');

        $this->actingAs($admin, 'tenant')
            ->post("/app/stripe-b/billing/checkout/{$target->id}", ['billing_cycle' => 'annual'])
            ->assertRedirect('https://checkout.stripe.com/c/pay/test-session');
    }

    public function test_checkout_blocked_when_plan_not_synced_to_stripe(): void
    {
        $plan = $this->makePlan([
            'stripe_product_id' => null,
            'stripe_monthly_price_id' => null,
            'stripe_annual_price_id' => null,
        ]);
        $tenant = $this->makeTenant('stripe-c');
        $admin = $this->makeAdmin($tenant, 'admin@stripe-c.test');

        $this->mockStripe()->shouldNotReceive('createCheckoutSession');

        $this->actingAs($admin, 'tenant')
            ->from('/app/stripe-c/billing')
            ->post("/app/stripe-c/billing/checkout/{$plan->id}", ['billing_cycle' => 'monthly'])
            ->assertRedirect('/app/stripe-c/billing')
            ->assertSessionHas('error');
    }

    public function test_checkout_blocked_for_the_plan_and_cycle_already_held(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-d', $plan);
        $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_STRIPE,
            'gateway_subscription_id' => 'sub_held',
            'stripe_status' => 'active',
        ]);
        $admin = $this->makeAdmin($tenant, 'admin@stripe-d.test');

        $this->mockStripe()->shouldNotReceive('createCheckoutSession');

        $this->actingAs($admin, 'tenant')
            ->from('/app/stripe-d/billing')
            ->post("/app/stripe-d/billing/checkout/{$plan->id}", ['billing_cycle' => 'monthly'])
            ->assertRedirect('/app/stripe-d/billing')
            ->assertSessionHas('error');
    }

    public function test_success_url_shows_processing_page_and_activates_nothing(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-e', $plan);
        $sub = $this->makeSubscription($tenant, $plan, ['status' => Subscription::STATUS_TRIALING]);
        $admin = $this->makeAdmin($tenant, 'admin@stripe-e.test');

        $this->actingAs($admin, 'tenant')
            ->get('/app/stripe-e/billing/checkout/success?session_id=cs_fake')
            ->assertOk();

        // Nothing changed — activation is webhook-only.
        $this->assertSame(Subscription::STATUS_TRIALING, $sub->fresh()->status);
        $this->assertSame(0, SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)->count());
    }

    // ------------------------------------------------------------------
    // Webhook — signature + activation
    // ------------------------------------------------------------------

    public function test_webhook_with_invalid_signature_is_rejected_400(): void
    {
        $this->mockStripe()
            ->shouldReceive('constructWebhookEvent')
            ->once()
            ->andThrow(new \UnexpectedValueException('Invalid signature'));

        $this->postJson('/stripe/webhook', ['fake' => 'payload'])
            ->assertStatus(400);
    }

    public function test_checkout_completed_webhook_activates_the_subscription(): void
    {
        Event::fake([SubscriptionUpgraded::class]);

        $trialPlan = $this->makePlan(['is_free' => true]);
        $paidPlan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-f', $trialPlan, [
            'status' => Tenant::STATUS_TRIAL,
            'stripe_customer_id' => 'cus_f',
        ]);
        $oldSub = $this->makeSubscription($tenant, $trialPlan, [
            'status' => Subscription::STATUS_TRIALING,
            'trial_ends_at' => now()->addDays(5),
        ]);

        $event = $this->stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_f',
            'mode' => 'subscription',
            'customer' => 'cus_f',
            'client_reference_id' => (string) $tenant->id,
            'subscription' => 'sub_f',
            'metadata' => ['plan_id' => (string) $paidPlan->id, 'billing_cycle' => 'monthly'],
        ]);

        $this->mockStripe()->shouldReceive('constructWebhookEvent')->once()->andReturn($event);

        $this->postJson('/stripe/webhook', [])->assertOk();

        // Old subscription cancelled; the Stripe-billed one is active.
        $this->assertSame(Subscription::STATUS_CANCELLED, $oldSub->fresh()->status);

        $new = Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', 'sub_f')->first();
        $this->assertNotNull($new);
        $this->assertSame(Subscription::STATUS_ACTIVE, $new->status);
        $this->assertSame(Subscription::GATEWAY_STRIPE, $new->gateway);
        $this->assertSame($paidPlan->stripe_monthly_price_id, $new->stripe_price_id);
        $this->assertSame('active', $new->stripe_status);
        $this->assertSame('monthly', $new->billing_cycle);

        // Tenant promoted; trial over.
        $tenant->refresh();
        $this->assertSame($paidPlan->id, $tenant->plan_id);
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertNull($tenant->trial_ends_at);

        // The checkout payment was recorded.
        $payment = SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('subscription_id', $new->id)->first();
        $this->assertNotNull($payment);
        $this->assertSame(5000, $payment->amount);
        $this->assertSame(SubscriptionPayment::METHOD_STRIPE, $payment->method);
        $this->assertSame('cs_test_f', $payment->reference);

        Event::assertDispatched(SubscriptionUpgraded::class);
    }

    public function test_checkout_completed_webhook_is_idempotent_across_retries(): void
    {
        $paidPlan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-g', null, ['stripe_customer_id' => 'cus_g']);

        $event = $this->stripeEvent('checkout.session.completed', [
            'id' => 'cs_test_g',
            'mode' => 'subscription',
            'customer' => 'cus_g',
            'client_reference_id' => (string) $tenant->id,
            'subscription' => 'sub_g',
            'metadata' => ['plan_id' => (string) $paidPlan->id, 'billing_cycle' => 'annual'],
        ]);

        $this->mockStripe()->shouldReceive('constructWebhookEvent')->twice()->andReturn($event);

        $this->postJson('/stripe/webhook', [])->assertOk();
        $this->postJson('/stripe/webhook', [])->assertOk(); // Stripe retry

        $this->assertSame(1, Subscription::withoutGlobalScope(TenantScope::class)
            ->where('gateway_subscription_id', 'sub_g')->count());
        $this->assertSame(1, SubscriptionPayment::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)->count());
    }

    // ------------------------------------------------------------------
    // Webhook — lifecycle updates
    // ------------------------------------------------------------------

    public function test_payment_failed_webhook_marks_past_due_and_notifies_once(): void
    {
        Event::fake([SubscriptionPaymentFailed::class]);

        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-h', $plan);
        $sub = $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_STRIPE,
            'gateway_subscription_id' => 'sub_h',
            'stripe_status' => 'active',
        ]);

        $event = $this->stripeEvent('invoice.payment_failed', ['subscription' => 'sub_h']);
        $this->mockStripe()->shouldReceive('constructWebhookEvent')->twice()->andReturn($event);

        $this->postJson('/stripe/webhook', [])->assertOk();

        $sub->refresh();
        $this->assertSame('past_due', $sub->stripe_status);
        $this->assertSame(Subscription::STATUS_PAST_DUE, $sub->status);

        // A second failure event while ALREADY past_due does not re-notify.
        $this->postJson('/stripe/webhook', [])->assertOk();
        Event::assertDispatchedTimes(SubscriptionPaymentFailed::class, 1);
    }

    public function test_subscription_deleted_webhook_cancels_locally_and_demotes_tenant(): void
    {
        Event::fake([SubscriptionCancelled::class]);

        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-i', $plan);
        $sub = $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_STRIPE,
            'gateway_subscription_id' => 'sub_i',
            'stripe_status' => 'canceling',
        ]);

        $event = $this->stripeEvent('customer.subscription.deleted', [
            'id' => 'sub_i',
            'status' => 'canceled',
        ]);
        $this->mockStripe()->shouldReceive('constructWebhookEvent')->once()->andReturn($event);

        $this->postJson('/stripe/webhook', [])->assertOk();

        $sub->refresh();
        $this->assertSame(Subscription::STATUS_CANCELLED, $sub->status);
        $this->assertSame('canceled', $sub->stripe_status);
        $this->assertNotNull($sub->cancelled_at);

        // No trial window left → cancelled (a live trial window means trial).
        $this->assertSame(Tenant::STATUS_CANCELLED, $tenant->fresh()->status);

        Event::assertDispatched(SubscriptionCancelled::class);
    }

    // ------------------------------------------------------------------
    // Cancellation (tenant-initiated)
    // ------------------------------------------------------------------

    public function test_admin_can_request_cancellation_at_period_end(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-j', $plan);
        $sub = $this->makeSubscription($tenant, $plan, [
            'gateway' => Subscription::GATEWAY_STRIPE,
            'gateway_subscription_id' => 'sub_j',
            'stripe_status' => 'active',
        ]);
        $admin = $this->makeAdmin($tenant, 'admin@stripe-j.test');

        $this->mockStripe()
            ->shouldReceive('cancelSubscription')
            ->once()
            ->with('sub_j')
            ->andReturnTrue();

        $this->actingAs($admin, 'tenant')
            ->from('/app/stripe-j/billing')
            ->post('/app/stripe-j/billing/cancel')
            ->assertRedirect('/app/stripe-j/billing')
            ->assertSessionHas('success');

        // Still ACTIVE locally — only flagged as winding down; the deleted
        // webhook performs the real cancellation when the period lapses.
        $sub->refresh();
        $this->assertSame(Subscription::STRIPE_STATUS_CANCELING, $sub->stripe_status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
    }

    public function test_cancellation_without_a_stripe_subscription_is_rejected(): void
    {
        $plan = $this->makePlan();
        $tenant = $this->makeTenant('stripe-k', $plan);
        $this->makeSubscription($tenant, $plan); // manual/no-gateway subscription
        $admin = $this->makeAdmin($tenant, 'admin@stripe-k.test');

        $this->mockStripe()->shouldNotReceive('cancelSubscription');

        $this->actingAs($admin, 'tenant')
            ->from('/app/stripe-k/billing')
            ->post('/app/stripe-k/billing/cancel')
            ->assertRedirect('/app/stripe-k/billing')
            ->assertSessionHas('error');
    }
}
