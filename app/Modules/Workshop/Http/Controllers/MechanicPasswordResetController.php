<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Auth\PortalPasswordResetController;

/**
 * Mechanic-portal password reset (forgot request + reset). Behaviour lives in
 * PortalPasswordResetController; this binds the mechanic broker/guard, login
 * redirect and views. Routed in routes/mechanic.php as GUEST routes (outside
 * auth:mechanic) — the tenant is bound by ResolveTenantForMechanic.
 *
 * Note: the mechanic broker resets the PASSWORD only (the PIN is a separate
 * convenience credential set by the tenant admin in the Mechanic CRUD).
 */
class MechanicPasswordResetController extends PortalPasswordResetController
{
    protected function broker(): string
    {
        return 'mechanic';
    }

    protected function guard(): string
    {
        return 'mechanic';
    }

    protected function loginRouteName(): string
    {
        return 'mechanic.login';
    }

    protected function requestView(): string
    {
        return 'Workshop/Mechanic/ForgotPassword';
    }

    protected function resetView(): string
    {
        return 'Workshop/Mechanic/ResetPassword';
    }
}
