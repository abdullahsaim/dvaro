<?php

namespace App\Http\Requests\Profile;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * Base for all profile-settings requests. The profile routes are mounted inside
 * EACH guard's authenticated group, so exactly one guard is active by the time
 * validation runs — resolved the same way UserPreferenceController does it.
 *
 * currentCredentialRule() is the ONE place the "prove it is really you" check
 * lives: ALWAYS Hash::check against the stored hash — NEVER Auth::attempt
 * (attempt would re-run provider lookups/rate limits and, for mechanics, only
 * checks the password path). Mechanics may be PIN-only (password nullable), so
 * the rule accepts the current password OR the current PIN, whichever is set —
 * both are login credentials of equal standing (client-approved decision).
 */
abstract class ProfileFormRequest extends FormRequest
{
    /**
     * Guards that own a profile page, in resolution order (mirrors
     * UserPreferenceController::GUARDS).
     */
    private const GUARDS = ['tenant', 'customer', 'mechanic', 'superadmin'];

    public function authorize(): bool
    {
        return $this->profileUser() !== null;
    }

    /**
     * The authenticated user of whichever guard is active on this route.
     */
    protected function profileUser(): ?Authenticatable
    {
        foreach (self::GUARDS as $guard) {
            $user = $this->user($guard);

            if ($user !== null) {
                return $user;
            }
        }

        return null;
    }

    /**
     * Validation rule verifying the submitted value against the user's current
     * password — or, for mechanics, their current PIN (either credential
     * proves identity; a PIN-only mechanic has no password hash to check).
     */
    protected function currentCredentialRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $user = $this->profileUser();

            $password = $user?->password;
            if (is_string($password) && $password !== '' && Hash::check((string) $value, $password)) {
                return;
            }

            // Only the Mechanic model carries a pin; on every other guard the
            // attribute is absent (null) and this branch is skipped.
            $pin = $user?->pin ?? null;
            if (is_string($pin) && $pin !== '' && Hash::check((string) $value, $pin)) {
                return;
            }

            $fail(__('profile.current_password_incorrect'));
        };
    }
}
