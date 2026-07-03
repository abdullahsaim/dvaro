<?php

namespace App\Modules\SaasCore\Providers;

use App\Contracts\PaymentProviderInterface;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use RuntimeException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe implementation of the payment provider contract — the ONLY place in
 * the codebase that touches \Stripe\* classes. Controllers, services and
 * actions must go through this provider, never the SDK.
 *
 * Also exposes the Stripe-admin product/price operations used by
 * StripeSyncService (deliberately NOT on PaymentProviderInterface — they are
 * platform sync operations, not payment operations, and PayPal has no
 * equivalent).
 */
class StripePaymentProvider implements PaymentProviderInterface
{
    private ?StripeClient $client = null;

    /**
     * LAZY: the client is built on first use, not at construction — the
     * provider is resolved via DI on routes that may never touch Stripe
     * (e.g. a request that fails authorization first), and an unconfigured
     * secret must surface as a call-time failure, not a container 500.
     */
    private function client(): StripeClient
    {
        if ($this->client !== null) {
            return $this->client;
        }

        $secret = (string) config('services.stripe.secret');

        if ($secret === '') {
            throw new RuntimeException('STRIPE_SECRET is not configured.');
        }

        return $this->client = new StripeClient($secret);
    }

    /**
     * One-off charges are not part of subscription billing — tenant checkout
     * is Checkout-Session based. (Customer-invoice payments are a later
     * session and will get their own implementation.)
     */
    public function charge(int $amount, string $currency = 'AUD'): mixed
    {
        throw new RuntimeException('Direct charges are not supported for subscription billing — use createCheckoutSession().');
    }

    /**
     * Create a hosted Checkout Session (mode: subscription) and return its URL.
     *
     * Creates the tenant's Stripe Customer on first use and persists the id
     * (reused on every later checkout). The tenant id rides along as
     * client_reference_id AND metadata so webhook handling can resolve the
     * tenant even if the customer lookup ever fails.
     */
    public function createCheckoutSession(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $priceId = $plan->stripePriceIdFor($billingCycle);

        if ($priceId === null) {
            throw new RuntimeException(
                "Plan [{$plan->slug}] has no Stripe price for the {$billingCycle} cycle — run stripe:sync-plans first."
            );
        }

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->ensureCustomer($tenant),
            'client_reference_id' => (string) $tenant->id,
            'line_items' => [
                ['price' => $priceId, 'quantity' => 1],
            ],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'plan_id' => (string) $plan->id,
                'billing_cycle' => $billingCycle,
            ],
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_id' => (string) $plan->id,
                ],
            ],
        ]);

        return (string) $session->url;
    }

    /**
     * Verify the webhook signature and decode the event. Throws
     * \Stripe\Exception\SignatureVerificationException (or UnexpectedValueException
     * on a malformed payload) — callers turn any throw into a 400.
     */
    public function constructWebhookEvent(string $payload, string $signature): object
    {
        return Webhook::constructEvent(
            $payload,
            $signature,
            (string) config('services.stripe.webhook_secret'),
        );
    }

    /**
     * Cancel at period end — the tenant keeps access until the paid period
     * lapses; Stripe then fires customer.subscription.deleted, which performs
     * the final local cancellation.
     */
    public function cancelSubscription(string $gatewaySubscriptionId): bool
    {
        $subscription = $this->client()->subscriptions->update($gatewaySubscriptionId, [
            'cancel_at_period_end' => true,
        ]);

        return (bool) $subscription->cancel_at_period_end;
    }

    /**
     * The tenant's Stripe Customer id, creating the Customer on first use.
     * Billing email is the tenant's (oldest) admin — the same recipient every
     * ops notification uses.
     */
    private function ensureCustomer(Tenant $tenant): string
    {
        if ($tenant->stripe_customer_id !== null) {
            return $tenant->stripe_customer_id;
        }

        $adminEmail = $tenant->users()
            ->where('role', TenantUser::ROLE_ADMIN)
            ->oldest('id')
            ->value('email');

        $customer = $this->client()->customers->create(array_filter([
            'name' => $tenant->name,
            'email' => $adminEmail,
            'metadata' => ['tenant_id' => (string) $tenant->id],
        ]));

        $tenant->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    // ------------------------------------------------------------------
    // Stripe-admin operations for StripeSyncService (not on the interface).
    // ------------------------------------------------------------------

    /**
     * Create a Product mirroring a plan; returns the product id.
     */
    public function createProduct(Plan $plan): string
    {
        $params = [
            'name' => $plan->name,
            'metadata' => ['plan_id' => (string) $plan->id, 'plan_slug' => $plan->slug],
        ];

        if (filled($plan->description)) {
            $params['description'] = $plan->description;
        }

        $product = $this->client()->products->create($params);

        return $product->id;
    }

    /**
     * Keep an existing Product's name/description current with the plan.
     */
    public function updateProduct(string $productId, Plan $plan): void
    {
        $this->client()->products->update($productId, [
            'name' => $plan->name,
            // Stripe clears a description via empty string, not null-omission.
            'description' => filled($plan->description) ? $plan->description : '',
        ]);
    }

    /**
     * Create a recurring Price (amount in cents) under a Product; returns the
     * price id. $interval is 'month' or 'year'.
     */
    public function createPrice(string $productId, int $amountCents, string $interval, string $currency = 'aud'): string
    {
        $price = $this->client()->prices->create([
            'product' => $productId,
            'unit_amount' => $amountCents,
            'currency' => $currency,
            'recurring' => ['interval' => $interval],
        ]);

        return $price->id;
    }

    /**
     * The unit amount (cents) of an existing Price — used by the sync to
     * detect a plan price change (Stripe Prices are immutable).
     */
    public function priceAmount(string $priceId): int
    {
        return (int) $this->client()->prices->retrieve($priceId)->unit_amount;
    }

    /**
     * Archive a Price that no longer matches the plan (immutable — replaced,
     * never edited). Existing subscriptions on it keep billing.
     */
    public function archivePrice(string $priceId): void
    {
        $this->client()->prices->update($priceId, ['active' => false]);
    }
}
