<?php

namespace App\Modules\CRM\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * The tenant's PUBLIC lead form (one per tenant): its secret token, settings,
 * public/embed URLs, embed snippet, QR code, framing policy and the "human
 * pacing" start token.
 *
 * Settings live in tenant.settings:
 *   lead_form_enabled          bool   (default true)
 *   lead_form_intro            string (optional intro shown above the form)
 *   lead_form_allowed_domains  list   (origins allowed to embed; [] = any site)
 *
 * The token lives in tenants.lead_form_token (unique, indexed). Regenerating it
 * immediately invalidates every previously shared link, QR code and embed.
 */
class LeadFormService extends BaseService
{
    /** A human can't fill the form faster than this (seconds). */
    public const MIN_FILL_SECONDS = 3;

    /** The start token expires after this (a stale open tab must reload). */
    public const MAX_FILL_SECONDS = 86400;

    public const MAX_ALLOWED_DOMAINS = 10;

    /**
     * The tenant for a public URL, or null when the slug/token don't match,
     * the form is switched off, or the tenant is not operating. Token compared
     * in constant time.
     */
    public function resolvePublic(string $slug, string $token): ?Tenant
    {
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null
            || blank($tenant->lead_form_token)
            || ! hash_equals($tenant->lead_form_token, $token)
            || in_array($tenant->status, [Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED], true)
            || ! $this->settings($tenant)['enabled']) {
            return null;
        }

        return $tenant;
    }

    /** The tenant's token, generating one on first use. */
    public function ensureToken(Tenant $tenant): string
    {
        if (blank($tenant->lead_form_token)) {
            return $this->regenerate($tenant);
        }

        return $tenant->lead_form_token;
    }

    /** New token — every old link / QR / embed stops working immediately. */
    public function regenerate(Tenant $tenant): string
    {
        $token = Str::random(40);
        $tenant->forceFill(['lead_form_token' => $token])->save();

        return $token;
    }

    /**
     * @return array{enabled: bool, intro: string, allowed_domains: list<string>}
     */
    public function settings(Tenant $tenant): array
    {
        $s = $tenant->settings ?? [];

        return [
            'enabled' => (bool) ($s['lead_form_enabled'] ?? true),
            'intro' => (string) ($s['lead_form_intro'] ?? ''),
            'allowed_domains' => array_values(array_filter((array) ($s['lead_form_allowed_domains'] ?? []))),
        ];
    }

    /**
     * Persist settings. Domains are normalised to origins (scheme://host[:port]);
     * anything unparseable is dropped (the request validates first).
     *
     * @param  list<string>  $domains
     */
    public function updateSettings(Tenant $tenant, bool $enabled, ?string $intro, array $domains): void
    {
        $origins = collect($domains)
            ->map(fn (string $d) => self::normalizeOrigin($d))
            ->filter()
            ->unique()
            ->take(self::MAX_ALLOWED_DOMAINS)
            ->values()
            ->all();

        $tenant->settings = [
            ...($tenant->settings ?? []),
            'lead_form_enabled' => $enabled,
            'lead_form_intro' => trim((string) $intro),
            'lead_form_allowed_domains' => $origins,
        ];
        $tenant->save();
    }

    /**
     * "example.com.au" / "https://www.example.com.au/contact" → origin, or
     * null if it isn't a usable http(s) host. Bare hosts default to https.
     */
    public static function normalizeOrigin(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $value)) {
            $value = 'https://'.$value;
        }

        $parts = parse_url($value);
        $host = strtolower($parts['host'] ?? '');

        if ($host === '' || ! preg_match('/^[a-z0-9.-]+$/', $host) || (! str_contains($host, '.') && $host !== 'localhost')) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }

    public function publicUrl(Tenant $tenant): string
    {
        return route('crm.lead-form.show', [
            'tenant_slug' => $tenant->slug,
            'token' => $this->ensureToken($tenant),
        ]);
    }

    public function embedUrl(Tenant $tenant): string
    {
        return route('crm.lead-form.embed', [
            'tenant_slug' => $tenant->slug,
            'token' => $this->ensureToken($tenant),
        ]);
    }

    /**
     * Copy-paste snippet for the tenant's website: an iframe + a tiny script
     * that (1) passes the host page URL through as `ref` and (2) auto-resizes
     * the iframe from the height the form posts via postMessage (origin-checked).
     */
    public function embedSnippet(Tenant $tenant): string
    {
        $src = $this->embedUrl($tenant);
        $id = 'dvaro-lead-form-'.$tenant->slug;
        $title = e(__('common.crm.lead_form_iframe_title', ['company' => $tenant->name]));

        // The accepted message origin is derived from the iframe's own src in
        // the browser, so it can never drift from where the form is served
        // (e.g. a mis-set APP_URL).
        return <<<HTML
<iframe id="{$id}" data-src="{$src}" title="{$title}" style="width:100%;height:760px;border:0;" loading="lazy"></iframe>
<script>
(function () {
  var f = document.getElementById('{$id}');
  var src = f.getAttribute('data-src');
  var origin = new URL(src).origin;
  f.src = src + '?ref=' + encodeURIComponent(location.href);
  window.addEventListener('message', function (e) {
    if (e.origin !== origin || e.source !== f.contentWindow || !e.data || e.data.type !== 'dvaro-lead-form:height') return;
    f.style.height = e.data.height + 'px';
  });
})();
</script>
HTML;
    }

    /**
     * CSP frame-ancestors for the EMBED page: the tenant's allowed origins, or
     * any site when none are configured. (Every other page is 'self' only —
     * see SecurityHeaders middleware.)
     */
    public function frameAncestors(?Tenant $tenant): string
    {
        $domains = $tenant !== null ? $this->settings($tenant)['allowed_domains'] : [];

        return $domains === [] ? '*' : "'self' ".implode(' ', $domains);
    }

    /** Encrypted "form rendered at" timestamp for the human-pacing check. */
    public function issueStartToken(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * True when the start token is genuine and the form took a human amount of
     * time (MIN_FILL_SECONDS … MAX_FILL_SECONDS) to fill.
     */
    public function isHumanPaced(?string $startToken): bool
    {
        if (blank($startToken)) {
            return false;
        }

        try {
            $startedAt = (int) Crypt::decryptString($startToken);
        } catch (DecryptException) {
            return false;
        }

        $elapsed = now()->getTimestamp() - $startedAt;

        return $elapsed >= self::MIN_FILL_SECONDS && $elapsed <= self::MAX_FILL_SECONDS;
    }

    /** Print-ready SVG QR code of the public form URL (generated on the fly). */
    public function qrSvg(Tenant $tenant): string
    {
        return (string) QrCode::format('svg')->size(512)->margin(2)->generate($this->publicUrl($tenant));
    }
}
