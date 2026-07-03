<?php

namespace App\Policies;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Authorization for the self-service profile pages (all four guards: tenant,
 * customer, mechanic, superadmin).
 *
 * A profile action only ever touches auth($guard)->user() — the "own record"
 * property is structural, so any authenticated user passes. The gate still
 * exists (rather than skipping authorization) to keep the load-bearing
 * Gate::forUser(auth($guard)->user())->authorize(...) pattern uniform across
 * every controller action, and as the hook for future restrictions (e.g. a
 * tenant setting locking staff email changes).
 *
 * Model-less ability → registered as a Gate::define delegation in
 * AppServiceProvider (like BillingPolicy / SuperAdminPolicy), not Gate::policy.
 * Typed Authenticatable so all four guard models pass through one policy.
 */
class ProfilePolicy
{
    public function manageOwnProfile(Authenticatable $user): bool
    {
        return true;
    }
}
