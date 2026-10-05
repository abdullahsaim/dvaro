<?php

namespace App\Modules\SuperAdmin\Services;

use App\Modules\SuperAdmin\Models\PlatformCredential;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Read/write access to platform-wide credentials (the platform_credentials
 * table) — Stripe/PayPal secrets, SMTP password, AI/SMS API keys, etc.
 *
 * Every value is Crypt::encryptString()'d at rest and decrypted only inside
 * this service. A value set here OVERRIDES the equivalent .env/config value
 * (applied at boot by PlatformCredentialOverrideServiceProvider) — leaving a
 * credential unset here means the app keeps using .env, so nothing breaks on
 * the day this feature ships.
 *
 * An empty/blank submitted value is NEVER treated as "clear this credential" —
 * that would make it impossible to update one field in a form without
 * resending every other secret in plaintext. Clearing is a separate,
 * explicit set($key, null).
 */
class PlatformCredentialService
{
    private const CACHE_PREFIX = 'platform_credentials:';

    private const CACHE_ALL_KEY = self::CACHE_PREFIX.'__all__';

    /**
     * Shorter than PlatformSettingsService's 24h — these are the kind of
     * value you want a sooner recovery path on if a mistaken entry needs
     * clearing, since set() already busts this immediately on write anyway.
     */
    private const CACHE_TTL = 3600;

    /**
     * The fixed set of credential keys this app understands, grouped by
     * integration for the Super Admin UI. Keep in sync with
     * PlatformCredentialOverrideServiceProvider::MAP (every key here must
     * have a mapping there, or setting it from the UI would do nothing).
     */
    public const GROUPS = [
        'stripe' => ['stripe_key', 'stripe_secret', 'stripe_webhook_secret'],
        'paypal' => ['paypal_mode', 'paypal_client_id', 'paypal_client_secret', 'paypal_webhook_id'],
        'mailgun' => ['mailgun_domain', 'mailgun_secret'],
        'resend' => ['resend_key'],
        'smtp' => ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption'],
        'groq' => ['groq_key'],
        'qwen' => ['qwen_key'],
        'deepseek' => ['deepseek_key'],
        'clicksend' => ['clicksend_username', 'clicksend_api_key', 'clicksend_whatsapp_number'],
        'cellcast' => ['cellcast_api_key'],
        'recaptcha' => ['recaptcha_site_key', 'recaptcha_secret_key'],
    ];

    /** Flat list of every known key, derived from GROUPS. */
    public static function allKeys(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    /**
     * A single credential's decrypted value, or null when unset or
     * undecryptable (e.g. APP_KEY rotated since it was written — treated as
     * "not configured" rather than a fatal error).
     */
    public function get(string $key): ?string
    {
        $encrypted = Cache::remember(self::CACHE_PREFIX.$key, self::CACHE_TTL, function () use ($key) {
            return PlatformCredential::query()->where('key', $key)->value('value') ?? '';
        });

        if ($encrypted === '' || $encrypted === null) {
            return null;
        }

        return $this->decrypt($key, $encrypted);
    }

    /**
     * Every known credential, decrypted, key => value — undecryptable or
     * unset keys are simply absent. ONE query (cached as a single blob) so
     * PlatformCredentialOverrideServiceProvider::boot() costs one cache read
     * per request rather than one per credential.
     */
    public function allDecrypted(): array
    {
        return Cache::remember(self::CACHE_ALL_KEY, self::CACHE_TTL, function () {
            return PlatformCredential::query()->get()
                ->mapWithKeys(function (PlatformCredential $c) {
                    $value = $this->decrypt($c->key, $c->value);

                    return $value === null ? [] : [$c->key => $value];
                })
                ->all();
        });
    }

    /**
     * Set (or clear, with $value === null) one credential. Busts both its own
     * cache entry and the combined allDecrypted() cache.
     */
    public function set(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            PlatformCredential::query()->where('key', $key)->delete();
        } else {
            PlatformCredential::updateOrCreate(['key' => $key], ['value' => Crypt::encryptString($value)]);
        }

        Cache::forget(self::CACHE_PREFIX.$key);
        Cache::forget(self::CACHE_ALL_KEY);
    }

    public function isSet(string $key): bool
    {
        return $this->get($key) !== null;
    }

    /**
     * Which of the given keys currently have a value — for the admin UI's
     * "configured" badges. NEVER returns the value itself.
     *
     * @return array<string, bool>
     */
    public function statusFor(array $keys): array
    {
        $all = $this->allDecrypted();

        return collect($keys)->mapWithKeys(fn (string $k) => [$k => array_key_exists($k, $all)])->all();
    }

    private function decrypt(string $key, string $encrypted): ?string
    {
        try {
            return Crypt::decryptString($encrypted);
        } catch (DecryptException $e) {
            Log::warning('PlatformCredentialService: failed to decrypt a stored credential', ['key' => $key]);

            return null;
        }
    }
}
