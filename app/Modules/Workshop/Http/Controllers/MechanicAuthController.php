<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Workshop\Http\Requests\MechanicLoginRequest;
use App\Modules\Workshop\Models\Mechanic;
use App\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mechanic-portal authentication (login / logout) for the 'mechanic' guard.
 *
 * The tenant is bound by ResolveTenantForMechanic before any action here runs,
 * so app('current_tenant') is always available and the (tenant-scoped) Mechanic
 * lookups resolve correctly. A mechanic signs in with EITHER a full password
 * (Auth::attempt) OR a quick PIN (explicit scoped lookup + Hash::check — never
 * Auth::attempt, which only checks the password column).
 *
 * After a QR scan by a logged-out mechanic, QrScanController stashes the scanned
 * vehicle token in the session; on successful login we send the mechanic
 * straight to that vehicle instead of the dashboard.
 */
class MechanicAuthController extends Controller
{
    /** Mirrors TenantAuthController: long enough that human-paced attempts lock out. */
    private const LOCKOUT_SECONDS = 900; // 15 minutes

    /** Session key holding the vehicle token a guest tried to scan before login. */
    public const INTENDED_TOKEN_KEY = 'mechanic.intended_vehicle_token';

    public function showLogin(): Response
    {
        return Inertia::render('Workshop/Mechanic/Login');
    }

    public function login(MechanicLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $tenant = app('current_tenant');

        $authenticated = $request->filled('password')
            ? $this->attemptPassword($request, $tenant->id)
            : $this->attemptPin($request, $tenant->id);

        if (! $authenticated) {
            RateLimiter::hit($request->throttleKey(), self::LOCKOUT_SECONDS);

            throw ValidationException::withMessages([
                'email' => __('common.auth.failed'),
            ]);
        }

        RateLimiter::clear($request->throttleKey());
        $request->session()->regenerate();

        Auth::guard('mechanic')->user()->update(['last_login_at' => now()]);

        return redirect()->to($this->destinationAfterLogin($request, $tenant->slug));
    }

    public function logout(Request $request): RedirectResponse
    {
        $tenant = app('current_tenant');

        Auth::guard('mechanic')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mechanic.login', ['tenant_slug' => $tenant->slug]);
    }

    /**
     * Password path: standard guard attempt, constrained to this tenant and to
     * active accounts (extra credential keys become WHERE conditions on top of
     * the automatic TenantScope).
     */
    private function attemptPassword(MechanicLoginRequest $request, int $tenantId): bool
    {
        return Auth::guard('mechanic')->attempt([
            'email' => $request->input('email'),
            'password' => $request->input('password'),
            'tenant_id' => $tenantId,
            'is_active' => true,
        ]);
    }

    /**
     * PIN path: explicit tenant-scoped lookup + Hash::check, then a manual
     * guard login. Auth::attempt is intentionally NOT used here — it only ever
     * verifies the password column.
     */
    private function attemptPin(MechanicLoginRequest $request, int $tenantId): bool
    {
        $mechanic = Mechanic::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('email', $request->input('email'))
            ->where('is_active', true)
            ->first();

        if ($mechanic === null || $mechanic->pin === null) {
            return false;
        }

        if (! Hash::check($request->input('pin'), $mechanic->pin)) {
            return false;
        }

        Auth::guard('mechanic')->login($mechanic);

        return true;
    }

    /**
     * Where to land after login: the scanned vehicle (if any was pending) or the
     * dashboard. The pending token is consumed (pull) so it fires only once.
     */
    private function destinationAfterLogin(Request $request, string $slug): string
    {
        $token = $request->session()->pull(self::INTENDED_TOKEN_KEY);

        if ($token !== null) {
            return route('mechanic.vehicle', ['tenant_slug' => $slug, 'token' => $token]);
        }

        return route('mechanic.dashboard', ['tenant_slug' => $slug]);
    }
}
