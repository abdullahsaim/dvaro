<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Contracts\CaptchaVerifierInterface;
use App\Http\Controllers\Controller;
use App\Modules\Notification\Http\Requests\UpdateNotificationSettingsRequest;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Integrations: the AI assistant provider (previously only settable
 * from tinker), the messaging providers, and a READ-ONLY status panel for the
 * things the PLATFORM controls (payments, reCAPTCHA) so a tenant can see
 * whether they are live without being able to change them.
 *
 * A provider is only "configured" when its credentials exist in the
 * environment — the same rule the provider factories apply at send time, so
 * this panel can never claim a provider works when it would silently fall back.
 */
class IntegrationSettingsController extends Controller
{
    /** AI providers a tenant may select. */
    public const AI_PROVIDERS = ['log', 'groq', 'qwen', 'deepseek'];

    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    public function show(CaptchaVerifierInterface $captcha): Response
    {
        $all = $this->settings->all(app('current_tenant'));

        return Inertia::render('Settings/Integrations', [
            'settings' => [
                'ai_provider' => $all['ai_provider'],
                'email_provider' => $all['email_provider'],
                'sms_provider' => $all['sms_provider'],
            ],
            'aiProviders' => self::AI_PROVIDERS,
            'emailProviders' => UpdateNotificationSettingsRequest::EMAIL_PROVIDERS,
            'smsProviders' => UpdateNotificationSettingsRequest::SMS_PROVIDERS,
            // Which providers actually have credentials on this server.
            // config(), never env(): env() returns null once the config is
            // cached in production, which would wrongly show "not configured".
            'configured' => [
                'groq' => filled(config('services.groq.key')),
                'qwen' => filled(config('services.qwen.key')),
                'deepseek' => filled(config('services.deepseek.key')),
                'mailgun' => filled(config('services.mailgun.secret')),
                'resend' => filled(config('services.resend.key')),
                'clicksend' => filled(config('services.clicksend.api_key')),
                'cellcast' => filled(config('services.cellcast.api_key')),
                'log' => true, // the always-available fallback
                'smtp' => true,
            ],
            'platform' => [
                'stripe' => filled(config('services.stripe.secret')),
                'recaptcha' => $captcha->siteKey() !== null,
            ],
            'canManage' => $this->isAdmin(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->isAdmin(), 403);

        $data = $request->validate([
            'ai_provider' => ['required', Rule::in(self::AI_PROVIDERS)],
            'email_provider' => ['required', Rule::in(UpdateNotificationSettingsRequest::EMAIL_PROVIDERS)],
            'sms_provider' => ['required', Rule::in(UpdateNotificationSettingsRequest::SMS_PROVIDERS)],
        ]);

        $this->settings->update(app('current_tenant'), $data, 'integrations');

        return back()->with('success', __('common.settings.integrations_saved'));
    }

    private function isAdmin(): bool
    {
        $user = auth('tenant')->user();

        return $user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN;
    }
}
