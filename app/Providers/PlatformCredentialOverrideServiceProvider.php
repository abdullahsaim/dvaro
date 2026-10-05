<?php

namespace App\Providers;

use App\Modules\SuperAdmin\Services\PlatformCredentialService;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Overrides config('services.*') and config('mail.mailers.smtp.*') with
 * whatever a super admin has set via the Credentials screen, ON TOP OF
 * whatever .env/config already loaded — so every existing provider class
 * (StripePaymentProvider, PaypalPaymentProvider, NotificationProviderFactory,
 * AiProviderFactory, the concrete email/SMS/WhatsApp/AI providers,
 * GoogleRecaptchaVerifier, ...) keeps reading config() exactly as before and
 * needed ZERO changes for this feature. An unset credential simply leaves
 * the .env-sourced value in place.
 *
 * Runs in boot() — after every ServiceProvider's register() phase, so this
 * is the last word on these config values before the app actually runs. On
 * PHP-FPM/Apache (no Octane here — CLAUDE.md) this re-runs on every single
 * request, which is exactly what's needed for a credential change in the
 * admin UI to take effect immediately with no cache:clear/restart.
 *
 * applyOverrides() is public and reused directly by boot() and by tests —
 * a service provider's boot() timing (it runs once per Application bootstrap,
 * which in a test suite is once per test's setUp(), BEFORE the test body sets
 * any credential) makes it awkward to assert through boot() alone.
 */
class PlatformCredentialOverrideServiceProvider extends ServiceProvider
{
    /**
     * credential key => config key. Keep in sync with
     * PlatformCredentialService::GROUPS — every key there must appear here,
     * or setting it from the UI would silently do nothing.
     */
    private const MAP = [
        'stripe_key' => 'services.stripe.key',
        'stripe_secret' => 'services.stripe.secret',
        'stripe_webhook_secret' => 'services.stripe.webhook_secret',

        'paypal_mode' => 'services.paypal.mode',
        'paypal_client_id' => 'services.paypal.client_id',
        'paypal_client_secret' => 'services.paypal.client_secret',
        'paypal_webhook_id' => 'services.paypal.webhook_id',

        'mailgun_domain' => 'services.mailgun.domain',
        'mailgun_secret' => 'services.mailgun.secret',

        'resend_key' => 'services.resend.key',

        'smtp_host' => 'mail.mailers.smtp.host',
        'smtp_port' => 'mail.mailers.smtp.port',
        'smtp_username' => 'mail.mailers.smtp.username',
        'smtp_password' => 'mail.mailers.smtp.password',
        'smtp_encryption' => 'mail.mailers.smtp.encryption',

        'groq_key' => 'services.groq.key',
        'qwen_key' => 'services.qwen.key',
        'deepseek_key' => 'services.deepseek.key',

        'clicksend_username' => 'services.clicksend.username',
        'clicksend_api_key' => 'services.clicksend.api_key',
        'clicksend_whatsapp_number' => 'services.clicksend.whatsapp_number',

        'cellcast_api_key' => 'services.cellcast.api_key',

        'recaptcha_site_key' => 'services.recaptcha.site_key',
        'recaptcha_secret_key' => 'services.recaptcha.secret_key',
    ];

    public function boot(): void
    {
        $this->applyOverrides();
    }

    public function applyOverrides(): void
    {
        try {
            $values = $this->app->make(PlatformCredentialService::class)->allDecrypted();
        } catch (Throwable $e) {
            // The table may not exist yet (fresh install, a request that lands
            // mid-`migrate`) or the DB may be briefly unreachable — never take
            // the whole app down for this. .env-sourced config stays as-is.
            return;
        }

        foreach (self::MAP as $credentialKey => $configKey) {
            if (! array_key_exists($credentialKey, $values)) {
                continue;
            }

            $value = $credentialKey === 'smtp_port' ? (int) $values[$credentialKey] : $values[$credentialKey];
            config([$configKey => $value]);
        }
    }
}
