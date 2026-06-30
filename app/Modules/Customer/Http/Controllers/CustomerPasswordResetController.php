<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Auth\PortalPasswordResetController;

/**
 * Customer-portal password reset (forgot request + reset). Behaviour lives in
 * PortalPasswordResetController; this binds the customer broker/guard, login
 * redirect and views. Routed in routes/customer.php as GUEST routes (outside
 * auth:customer) — the tenant is bound by ResolveTenantForCustomer.
 */
class CustomerPasswordResetController extends PortalPasswordResetController
{
    protected function broker(): string
    {
        return 'customer';
    }

    protected function guard(): string
    {
        return 'customer';
    }

    protected function loginRouteName(): string
    {
        return 'customer.login';
    }

    protected function requestView(): string
    {
        return 'Customer/Portal/ForgotPassword';
    }

    protected function resetView(): string
    {
        return 'Customer/Portal/ResetPassword';
    }
}
