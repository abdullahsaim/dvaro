<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notification\Services\TenantEmailVerificationNotifier;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Http\RedirectResponse;

/**
 * Email verification for the tenant guard — SOFT (CLAUDE.md decision): an
 * unverified admin keeps full access from the moment they sign up, so this
 * controller only ever marks the trail and nudges, it never blocks a route.
 *
 * verify() is reached cold from an inbox link — the 'signed' route middleware
 * is the only gate (no stored token, same pattern as password reset's broker
 * but simpler: the HMAC signature IS the credential, nothing to look up).
 * {tenant_slug} binds the tenant via TenantMiddleware before this runs, so
 * {id} resolves through TenantScope — a signed link can never verify a user
 * belonging to a DIFFERENT tenant even if the numeric id collided.
 */
class EmailVerificationController extends Controller
{
    public function verify(int $id, string $hash): RedirectResponse
    {
        $user = TenantUser::find($id);

        // A wrong/stale id or a hash that no longer matches the current email
        // (e.g. the address was since changed) — fail quietly, no stack trace.
        // TenantMiddleware already strips the {tenant_slug} route param after
        // binding, so the slug is read from current_tenant, not the route.
        if ($user === null || ! hash_equals(sha1($user->email), $hash)) {
            return redirect()->route('tenant.login', ['tenant_slug' => app('current_tenant')->slug])
                ->withErrors(['email' => __('common.verification.invalid_link')]);
        }

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        // Likely still logged in from signup (soft verification never logs
        // anyone out) — go straight back to the dashboard when so; otherwise
        // the sign-in page, since this link may be opened cold on another
        // device.
        if (auth('tenant')->id() === $user->id) {
            return redirect()
                ->route('tenant.dashboard', ['tenant_slug' => $user->tenant->slug])
                ->with('success', __('common.verification.verified'));
        }

        return redirect()
            ->route('tenant.login', ['tenant_slug' => $user->tenant->slug])
            ->with('success', __('common.verification.verified'));
    }

    /**
     * Resend the link to the SIGNED-IN user's own address — never an
     * arbitrary id, so this can only ever re-verify your own account.
     */
    public function resend(TenantEmailVerificationNotifier $notifier): RedirectResponse
    {
        $user = auth('tenant')->user();

        if ($user->email_verified_at !== null) {
            return back()->with('success', __('common.verification.already_verified'));
        }

        $notifier->send($user);

        return back()->with('success', __('common.verification.resent'));
    }
}
