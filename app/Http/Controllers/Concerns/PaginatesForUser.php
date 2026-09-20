<?php

namespace App\Http\Controllers\Concerns;

use App\Services\UserPreferences;
use Illuminate\Support\Facades\Auth;

/**
 * Lets every list screen honour "rows per page" from the signed-in person's own
 * preferences (Profile → Preferences), whichever guard they signed in on.
 *
 * Each call site keeps its own sensible fallback, so a screen that deliberately
 * shows 30 rows still shows 30 for anyone who has never chosen.
 */
trait PaginatesForUser
{
    /** Guards that own a preferences column, in resolution order. */
    private const PREFERENCE_GUARDS = ['tenant', 'customer', 'mechanic', 'superadmin'];

    protected function perPage(int $fallback = 15): int
    {
        foreach (self::PREFERENCE_GUARDS as $guard) {
            if (Auth::guard($guard)->check()) {
                return app(UserPreferences::class)->rowsPerPage(Auth::guard($guard)->user(), $fallback);
            }
        }

        return $fallback;
    }
}
