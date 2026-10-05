<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SuperAdmin\Http\Requests\IntegrationCredentialsRequest;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use App\Modules\SuperAdmin\Services\PlatformCredentialService;
use App\Services\PlatformActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super Admin → Settings → Credentials: platform-wide secrets for every
 * provider (Stripe/PayPal, Mailgun/Resend/SMTP, Groq/Qwen/DeepSeek,
 * ClickSend/Cellcast, reCAPTCHA) — previously .env-only, requiring an SSH
 * session + service restart for every change. Values set here OVERRIDE the
 * equivalent .env value (PlatformCredentialOverrideServiceProvider) and take
 * effect on the very next request; leaving a credential unset keeps using
 * .env, so nothing breaks the day this ships.
 *
 * Owner-level only (platformOwner) — same gate as the general platform
 * settings screen, and strictly higher-privilege than billing/support, since
 * this page can grant access to live payment gateways and outbound mail/SMS.
 *
 * The page NEVER receives a credential's actual value, only whether each is
 * currently set — see PlatformCredentialService::statusFor(). A field left
 * blank on submit is "unchanged", not "cleared" (see the Request's docblock);
 * clearing goes through the explicit `clear` list from the "Remove" action
 * next to a configured field.
 */
class IntegrationCredentialsController extends Controller
{
    public function __construct(
        private readonly PlatformCredentialService $credentials,
        private readonly PlatformActivityLogger $activity,
    ) {}

    public function show(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('platformOwner');

        return Inertia::render('SuperAdmin/Settings/Credentials', [
            'groups' => PlatformCredentialService::GROUPS,
            'configured' => $this->credentials->statusFor(PlatformCredentialService::allKeys()),
        ]);
    }

    public function update(IntegrationCredentialsRequest $request): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('platformOwner');

        $changed = [];

        foreach (PlatformCredentialService::allKeys() as $key) {
            $value = $request->input($key);

            if ($value === null || $value === '') {
                continue; // blank = unchanged, never an accidental clear
            }

            $this->credentials->set($key, $value);
            $changed[] = $key;
        }

        foreach ((array) $request->input('clear', []) as $key) {
            $this->credentials->set($key, null);
            $changed[] = $key;
        }

        if ($changed !== []) {
            // Only the KEY NAMES are logged — never a value, old or new, even
            // encrypted. A secret's content has no business in an audit trail.
            $this->activity->log(
                'platform_credentials.updated',
                PlatformActivityLog::SUBJECT_SETTINGS,
                new: ['keys_changed' => array_values(array_unique($changed))],
            );
        }

        return back()->with('success', __('common.superadmin.credentials_saved'));
    }
}
