<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a tenant-scoped query is executed without a tenant bound to
 * the container (app('current_tenant')).
 *
 * This is a programming/context error, not a user-facing condition. It is
 * intentionally NOT registered in the exception handler's dontReport() list,
 * so it surfaces fully (with stack trace when APP_DEBUG=true) in development.
 */
class TenantNotResolvedException extends RuntimeException
{
    public function __construct(
        string $message = 'No tenant resolved in the current context. A tenant-scoped query was executed outside of a tenant context.'
    ) {
        parent::__construct($message);
    }
}
