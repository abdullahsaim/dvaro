<?php

namespace App\Modules\SaasCore\Providers;

use App\Contracts\PaymentProviderInterface;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * PayPal implementation of the payment provider contract — the ONLY place in
 * the codebase that talks to the PayPal REST API. Controllers, services and
 * actions must go through this provider, never a raw HTTP call.
 *
 * No official PayPal SDK is installed (composer.json carries stripe/stripe-php
 * only) — PayPal's REST API is plain JSON over HTTP, so Laravel's own Http
 * client is used directly, same spirit as calling \Stripe\StripeClient.
 *
 * Also exposes the PayPal-admin product/plan operations used by
 * PaypalSyncService (deliberately NOT on PaymentProviderInterface — mirrors
 * StripePaymentProvider's equivalent split).
 */
class PaypalPaymentProvider implements PaymentProviderInterface
{
    /**
     * LAZY: credentials are only required at call time, not at container
     * resolution — mirrors StripePaymentProvider's client() guard.
     */
    private function baseUrl(): string
    {
        return config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    /**
     * An OAuth2 client-credentials access token, cached for its own lifetime
     * (minus a safety margin) so every call does not re-authenticate.
     */
    private function accessToken(): string
    {
        $clientId = (string) config('services.paypal.client_id');
        $secret = (string) config('services.paypal.client_secret');

        if ($clientId === '' || $secret === '') {
            throw new RuntimeException('PAYPAL_CLIENT_ID / PAYPAL_CLIENT_SECRET are not configured.');
        }

        return Cache::remember('paypal.access_token.'.config('services.paypal.mode'), 480, function () use ($clientId, $secret) {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->post($this->baseUrl().'/v1/oauth2/token', [
                    'grant_type' => 'client_credentials',
                ])
                ->throw();

            return (string) $response->json('access_token');
        });
    }

    private function client()
    {
        return Http::baseUrl($this->baseUrl())
            ->withToken($this->accessToken())
            ->acceptJson();
    }

    /**
     * One-off charges are not part of subscription billing — tenant checkout
     * is subscription based, same split as StripePaymentProvider.
     */
    public function charge(int $amount, string $currency = 'AUD'): mixed
    {
        throw new RuntimeException('Direct charges are not supported for subscription billing — use createCheckoutSession().');
    }

    /**
     * Create a PayPal billing subscription and return its approval URL (the
     * link the customer's browser must be redirected to, PayPal's equivalent
     * of a Stripe Checkout Session URL).
     *
     * The tenant id rides along as custom_id so webhook handling can resolve
     * the tenant without a stored PayPal customer id (PayPal subscriptions
     * carry no separate "Customer" object the way Stripe does).
     */
    public function createCheckoutSession(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        string $successUrl,
        string $cancelUrl,
    ): string {
        $planId = $plan->paypalPlanIdFor($billingCycle);

        if ($planId === null) {
            throw new RuntimeException(
                "Plan [{$plan->slug}] has no PayPal billing plan for the {$billingCycle} cycle — run paypal:sync-plans first."
            );
        }

        $response = $this->client()->post('/v1/billing/subscriptions', [
            'plan_id' => $planId,
            'custom_id' => (string) $tenant->id,
            'application_context' => [
                'brand_name' => 'DVARO',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
        ])->throw();

        $approveLink = collect($response->json('links') ?? [])
            ->firstWhere('rel', 'approve');

        if ($approveLink === null) {
            throw new RuntimeException('PayPal did not return an approval link for the new subscription.');
        }

        return (string) $approveLink['href'];
    }

    /**
     * Verify a webhook payload against PayPal's verify-webhook-signature API.
     * PayPal has no single HMAC signature (unlike Stripe) — the signature
     * bundle is a set of headers PaypalWebhookController hands over as-is.
     * Throws when PAYPAL_WEBHOOK_ID is unset or PayPal reports SUCCESS !==
     * verification_status; callers treat any throw as an invalid webhook.
     *
     * @param  array<string, string>  $signature  transmission_id, transmission_time, cert_url, auth_algo, transmission_sig headers
     */
    public function constructWebhookEvent(string $payload, array|string $signature): object
    {
        if (! is_array($signature)) {
            throw new RuntimeException('PayPal webhook verification requires the header bundle, not a single signature string.');
        }

        $webhookId = (string) config('services.paypal.webhook_id');

        if ($webhookId === '') {
            throw new RuntimeException('PAYPAL_WEBHOOK_ID is not configured.');
        }

        $event = json_decode($payload, associative: false, flags: JSON_THROW_ON_ERROR);

        $verification = $this->client()->post('/v1/notifications/verify-webhook-signature', [
            'transmission_id' => $signature['transmission_id'] ?? '',
            'transmission_time' => $signature['transmission_time'] ?? '',
            'cert_url' => $signature['cert_url'] ?? '',
            'auth_algo' => $signature['auth_algo'] ?? '',
            'transmission_sig' => $signature['transmission_sig'] ?? '',
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ])->throw();

        if ($verification->json('verification_status') !== 'SUCCESS') {
            throw new RuntimeException('PayPal webhook signature verification failed.');
        }

        return $event;
    }

    /**
     * Cancel immediately — PayPal's cancel endpoint has no "at period end"
     * option the way Stripe's does; access is revoked right away by PayPal.
     * The local row is still only flagged 'canceling' here (same as Stripe) —
     * the gateway's own BILLING.SUBSCRIPTION.CANCELLED webhook performs the
     * final local cancellation, keeping one sanctioned cancellation path.
     */
    public function cancelSubscription(string $gatewaySubscriptionId): bool
    {
        $this->client()->post("/v1/billing/subscriptions/{$gatewaySubscriptionId}/cancel", [
            'reason' => 'Cancelled by customer via DVARO billing portal.',
        ])->throw();

        return true;
    }

    /**
     * Change an existing subscription's plan via PayPal's "revise" endpoint.
     *
     * UNLIKE Stripe's always_invoice behaviour, PayPal's REST API has no
     * immediate-prorated-invoice equivalent: revising a subscription changes
     * the plan for FUTURE billing cycles only, with no synchronous charge
     * returned. amount_charged is therefore honestly always 0 for PayPal.
     * BillingController restricts the self-service in-place upgrade button to
     * Stripe subscriptions only (canUpgradeInPlace) for exactly this reason —
     * a PayPal subscriber changes plans by cancelling and re-subscribing via
     * createCheckoutSession() instead, where the new price is exact and
     * unambiguous. This method exists to satisfy the shared contract should a
     * future caller choose to use it, not because the current UI calls it.
     */
    public function changeSubscriptionPlan(string $gatewaySubscriptionId, string $newGatewayPriceId): array
    {
        $response = $this->client()->post("/v1/billing/subscriptions/{$gatewaySubscriptionId}/revise", [
            'plan_id' => $newGatewayPriceId,
        ])->throw();

        return [
            'current_period_start' => now()->toDateTimeImmutable(),
            'current_period_end' => now()->addMonth()->toDateTimeImmutable(),
            'amount_charged' => 0,
            'gateway_price_id' => $newGatewayPriceId,
        ];
    }

    // ------------------------------------------------------------------
    // PayPal-admin operations for PaypalSyncService (not on the interface).
    // ------------------------------------------------------------------

    /**
     * Create a catalog Product mirroring a plan; returns the product id.
     */
    public function createProduct(Plan $plan): string
    {
        $response = $this->client()->post('/v1/catalogs/products', [
            'name' => $plan->name,
            'description' => filled($plan->description) ? $plan->description : $plan->name,
            'type' => 'SERVICE',
            'category' => 'SOFTWARE',
        ])->throw();

        return (string) $response->json('id');
    }

    /**
     * Create a recurring billing Plan (amount in cents) under a Product;
     * returns the plan id. $interval is 'MONTH' or 'YEAR'.
     */
    public function createBillingPlan(string $productId, int $amountCents, string $interval, string $currency = 'AUD'): string
    {
        $response = $this->client()->post('/v1/billing/plans', [
            'product_id' => $productId,
            'name' => $productId.'-'.strtolower($interval),
            'billing_cycles' => [[
                'frequency' => [
                    'interval_unit' => $interval,
                    'interval_count' => 1,
                ],
                'tenure_type' => 'REGULAR',
                'sequence' => 1,
                'total_cycles' => 0, // 0 = infinite, until cancelled
                'pricing_scheme' => [
                    'fixed_price' => [
                        'value' => number_format($amountCents / 100, 2, '.', ''),
                        'currency_code' => $currency,
                    ],
                ],
            ]],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'payment_failure_threshold' => 3,
            ],
        ])->throw();

        return (string) $response->json('id');
    }

    /**
     * The fixed price (cents) of an existing billing Plan — used by the sync
     * to detect a plan price change (PayPal billing Plans are immutable on
     * price the same way Stripe Prices are).
     */
    public function billingPlanAmount(string $planId): int
    {
        $response = $this->client()->get("/v1/billing/plans/{$planId}")->throw();

        $cycle = collect($response->json('billing_cycles') ?? [])->first();
        $value = $cycle['pricing_scheme']['fixed_price']['value'] ?? '0';

        return (int) round(((float) $value) * 100);
    }

    /**
     * Deactivate a billing Plan that no longer matches the plan (immutable —
     * replaced, never edited). Existing subscriptions on it keep billing.
     */
    public function deactivateBillingPlan(string $planId): void
    {
        $this->client()->post("/v1/billing/plans/{$planId}/deactivate")->throw();
    }
}
