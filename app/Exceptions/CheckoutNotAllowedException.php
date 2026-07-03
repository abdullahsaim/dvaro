<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant cannot start a Stripe checkout for a plan — the plan is
 * inactive, free, not yet synced to Stripe, or the tenant already holds an
 * active Stripe subscription for that exact plan + cycle.
 *
 * Carries a USER-DISPLAYABLE (translated) message: StripeCheckoutController
 * catches it and flashes the message back to the billing page — it has no
 * render() because it never escapes the controller.
 */
class CheckoutNotAllowedException extends RuntimeException
{
}
