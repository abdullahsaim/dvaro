<?php

namespace App\Services;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Support\Facades\Storage;

/**
 * A company's identity on the documents it sends out: its logo, its colour and
 * its own details.
 *
 * Shared, because it is not the Invoice module's property — invoices and
 * agreements both put the same company at the top of the page, and anything
 * else DVARO prints later will want the same three things.
 *
 * Presentation only. Nothing here can reach a figure, a date or a signature.
 */
class TenantBranding
{
    /** Used when a company has set no colour of its own. */
    public const FALLBACK_COLOUR = '#0f172a';

    public function __construct(
        private readonly TenantSettingsService $settings,
    ) {}

    /**
     * Logo, colour, company details and date format, ready for a Blade.
     *
     * @return array<string, mixed>
     */
    public function forDocument(Tenant $tenant, ?string $logoOverride = null, ?string $colourOverride = null, bool $forScreen = false): array
    {
        $all = $this->settings->all($tenant);

        return [
            'logo' => $this->logo($logoOverride ?: ($all['logo_path'] ?? null), $forScreen),
            'accent' => $this->colour($colourOverride) ?? $this->colour($all['brand_colour']) ?? self::FALLBACK_COLOUR,
            'company' => [
                'name' => $all['legal_name'] ?: $tenant->name,
                'trading_as' => $all['legal_name'] ? $tenant->name : null,
                'abn' => $all['abn'],
                'address' => $all['address'],
                'phone' => $all['phone'],
                'email' => $all['email'],
                'website' => $all['website'],
            ],
            'date_format' => $all['date_format'],
        ];
    }

    /**
     * Where a document should point its <img> at.
     *
     * For a PDF: an ABSOLUTE filesystem path, which dompdf reads directly (its
     * chroot is the project root, and the public disk lives inside it).
     * For an on-screen preview: a ROOT-RELATIVE url, because a browser cannot
     * open a filesystem path — and deliberately not $disk->url(), which is
     * built from APP_URL and so drops the port in local dev.
     *
     * The stored path is only ever written by an upload endpoint, but it is
     * re-checked here so a hand-edited settings row can never reach outside the
     * public disk or point at a file that has since been deleted.
     */
    public function logo(?string $path, bool $forScreen = false): ?string
    {
        if (blank($path) || str_contains($path, '..')) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            return null;
        }

        if (! $forScreen) {
            return $disk->path($path);
        }

        return parse_url($disk->url($path), PHP_URL_PATH) ?: $disk->url($path);
    }

    /** A valid six-digit hex colour, or null. */
    public function colour(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
            ? strtolower($value)
            : null;
    }
}
