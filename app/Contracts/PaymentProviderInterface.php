<?php

namespace App\Contracts;

/**
 * Contract for payment providers (Stripe, PayPal).
 *
 * Never call provider SDKs directly — resolve a provider for the tenant and
 * charge through this interface. Webhook signatures must always be verified.
 */
interface PaymentProviderInterface
{
    public function charge(int $amount, string $currency = 'AUD'): mixed;
}
