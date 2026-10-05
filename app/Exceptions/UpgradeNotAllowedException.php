<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant cannot self-service upgrade their plan in place — no
 * active gateway subscription to change, or already on the requested plan.
 *
 * Carries a USER-DISPLAYABLE message: StripeCheckoutController::upgrade()
 * catches it and flashes the message back to the billing page. Deliberately
 * NOT caught by the same branch as a genuine gateway/network failure (a plain
 * Throwable) — those must never leak their raw message to the browser, while
 * this one is safe to show verbatim. Mirrors CheckoutNotAllowedException.
 */
class UpgradeNotAllowedException extends RuntimeException {}
