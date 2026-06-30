<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Auth\PortalPasswordResetController;

/**
 * Tenant-portal password reset (forgot request + reset). All behaviour comes
 * from PortalPasswordResetController; this only binds the tenant broker/guard,
 * the login redirect, and the two Inertia views. Routed in routes/tenant.php as
 * GUEST routes (outside auth:tenant) — the tenant is still bound by
 * TenantMiddleware, which is what the broker's scoped user lookup needs.
 */
class TenantPasswordResetController extends PortalPasswordResetController
{
    protected function broker(): string
    {
        return 'tenant';
    }

    protected function guard(): string
    {
        return 'tenant';
    }

    protected function loginRouteName(): string
    {
        return 'tenant.login';
    }

    protected function requestView(): string
    {
        return 'Tenant/ForgotPassword';
    }

    protected function resetView(): string
    {
        return 'Tenant/ResetPassword';
    }
}
