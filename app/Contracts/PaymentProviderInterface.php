<?php

namespace App\Contracts;

use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;

/**
 * Contract for payment providers (Stripe, PayPal).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant and
 * charge through this interface. Webhook signatures must always be verified.
 *
 * Return types stay gateway-agnostic (object, string ids) so a future PayPal
 * implementation can satisfy the same contract without leaking SDK classes.
 */
interface PaymentProviderInterface
{
    public function charge(int $amount, string $currency = 'AUD'): mixed;

    /**
     * Create a hosted checkout session for a subscription and return the URL
     * the customer's browser must be redirected to.
     *
     * @param  string  $billingCycle  Subscription::BILLING_MONTHLY|BILLING_ANNUAL
     */
    public function createCheckoutSession(
        Tenant $tenant,
        Plan $plan,
        string $billingCycle,
        string $successUrl,
        string $cancelUrl,
    ): string;

    /**
     * Verify a webhook payload against its signature and return the decoded
     * event object. MUST throw when the signature is invalid — callers abort
     * 400 on any throw. (Stripe returns \Stripe\Event.)
     */
    public function constructWebhookEvent(string $payload, string $signature): object;

    /**
     * Cancel a gateway subscription (at period end — the customer keeps access
     * until the paid period lapses; the gateway's deletion webhook performs the
     * final local cancellation).
     */
    public function cancelSubscription(string $gatewaySubscriptionId): bool;

    /**
     * Change an EXISTING subscription to a different plan price, immediately,
     * charging (or crediting) the prorated difference for the rest of the
     * current period right away — never deferred to the next invoice.
     *
     * Returns a gateway-agnostic result the caller can trust without knowing
     * which gateway produced it: the new current period bounds (so the local
     * Subscription row can be kept in sync immediately, not only once the
     * gateway's own webhook arrives) and what was actually charged for the
     * proration (0 when the change was a pure credit, never charged).
     *
     * @return array{current_period_start: \DateTimeInterface, current_period_end: \DateTimeInterface, amount_charged: int, gateway_price_id: string}
     */
    public function changeSubscriptionPlan(string $gatewaySubscriptionId, string $newGatewayPriceId): array;
}
