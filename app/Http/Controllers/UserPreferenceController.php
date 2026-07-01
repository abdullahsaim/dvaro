<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

/**
 * Per-user UI preferences shared across all four authenticated guards
 * (tenant, customer, mechanic, superadmin).
 *
 * The route is mounted inside EACH guard's authenticated group, so by the time
 * we get here exactly one of those guards is active. We detect it and persist
 * the preference on that guard's user model — there is no single User table.
 */
class UserPreferenceController extends Controller
{
    /**
     * Guards that own a color_mode column, in resolution order.
     */
    private const GUARDS = ['tenant', 'customer', 'mechanic', 'superadmin'];

    /**
     * Persist the authenticated user's dark/light preference.
     *
     * Front-end already applied the change optimistically (localStorage + the
     * `dark` class); this call just makes it durable on the profile so the
     * preference follows the user to a new device/browser.
     */
    public function updateColorMode(Request $request): Response
    {
        $validated = $request->validate([
            'color_mode' => ['required', 'string', 'in:light,dark,system'],
        ]);

        $user = $this->resolveAuthenticatedUser();

        // No authenticated user on any guard — nothing to persist. The
        // localStorage copy still drives the UI, so this is a silent no-op.
        if ($user !== null) {
            $user->forceFill(['color_mode' => $validated['color_mode']])->save();
        }

        // XHR-only endpoint (called from the useColorMode composable). 204 keeps
        // it silent — no redirect to follow, no Inertia reload.
        return response()->noContent();
    }

    /**
     * Return the user from whichever guard is currently authenticated.
     */
    private function resolveAuthenticatedUser()
    {
        foreach (self::GUARDS as $guard) {
            if (Auth::guard($guard)->check()) {
                return Auth::guard($guard)->user();
            }
        }

        return null;
    }
}
