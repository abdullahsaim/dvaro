<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reusable password-reset flow for the tenant-scoped portal guards (tenant,
 * customer, mechanic). One concrete subclass per guard supplies the broker name,
 * the guard, the login route and the two Inertia views; everything else — the
 * forgot-request, the email dispatch, the reset form and the actual reset — lives
 * here so the three guards behave identically.
 *
 * Tenant context is already bound by the route group's resolver (TenantMiddleware
 * / ResolveTenantForCustomer / ResolveTenantForMechanic), so the broker's user
 * lookup is automatically TenantScope-constrained — a reset can only ever touch
 * an account of the tenant in the URL. The reset LINK embeds that slug in its
 * path (the model's ResetsPasswordWithinTenant trait builds it), which is what
 * makes the bound tenant correct when the link is clicked cold from an inbox.
 *
 * Super admin is intentionally excluded — its password reset is manual/support
 * only (platform convention).
 */
abstract class PortalPasswordResetController extends Controller
{
    /** The password broker name (config/auth.php passwords.*). */
    abstract protected function broker(): string;

    /** The auth guard to log the user in on a successful reset. */
    abstract protected function guard(): string;

    /** The guard's login route name (redirect target after a reset). */
    abstract protected function loginRouteName(): string;

    /** Inertia page for the "forgot password" request form. */
    abstract protected function requestView(): string;

    /** Inertia page for the "set a new password" form. */
    abstract protected function resetView(): string;

    public function showRequestForm(): Response
    {
        // The tenant slug is shared into Inertia by the route group's resolver.
        return Inertia::render($this->requestView());
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // The broker resolves the user through the bound-tenant provider query
        // (TenantScope), so only this tenant's account can be matched. We ignore
        // the specific status and always flash a neutral message to avoid leaking
        // whether an address is registered (user enumeration).
        Password::broker($this->broker())->sendResetLink(
            $request->only('email')
        );

        return back()->with('success', __('passwords.sent_generic'));
    }

    public function showResetForm(Request $request, string $token): Response
    {
        return Inertia::render($this->resetView(), [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::broker($this->broker())->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (CanResetPassword $user, string $password): void {
                // The model's 'hashed' cast hashes on save.
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        $slug = app('current_tenant')->slug;

        if ($status === Password::PASSWORD_RESET) {
            return redirect()
                ->route($this->loginRouteName(), ['tenant_slug' => $slug])
                ->with('success', __('passwords.reset'));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
