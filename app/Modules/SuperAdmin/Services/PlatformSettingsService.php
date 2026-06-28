<?php

namespace App\Modules\SuperAdmin\Services;

use App\Modules\SuperAdmin\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Read/write access to platform-wide settings (the platform_settings table).
 *
 * Always cache-first: get() never hits the DB once a key is warm. Values are
 * stored as text and cast on read by the row's `type` column. set() updates the
 * DB and clears that one key's cache entry so the next read re-warms it.
 *
 * Platform settings are global (no tenant) — this service is used only by the
 * super admin panel and platform-level logic, never tenant-scoped requests.
 */
class PlatformSettingsService
{
    private const CACHE_PREFIX = 'platform_settings:';

    /**
     * Cache TTL. Settings rarely change and set() busts the key explicitly, so
     * a long TTL is safe; it mainly bounds drift if a row is edited directly.
     */
    private const CACHE_TTL = 86400; // 24h

    /**
     * Resolve a setting, cast to its declared type. Cache-first.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        // Cache::remember stores the cast value. A genuine "missing key" is
        // cached as the sentinel below so we don't re-query on every miss.
        $value = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            $setting = PlatformSetting::query()->where('key', $key)->first();

            if ($setting === null) {
                return ['__missing__' => true];
            }

            return ['value' => $this->cast($setting->value, $setting->type)];
        });

        if (isset($value['__missing__'])) {
            return $default;
        }

        return $value['value'] ?? $default;
    }

    /**
     * Update a setting's value and bust its cache entry.
     *
     * The row must already exist (settings are seeded by migration). The stored
     * `type` is preserved; the value is serialised back to text for storage.
     */
    public function set(string $key, mixed $value): void
    {
        $setting = PlatformSetting::query()->where('key', $key)->first();

        if ($setting === null) {
            return;
        }

        $setting->value = $this->serialise($value, $setting->type);
        $setting->save();

        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * All settings as a key => cast-value map (for the settings screen).
     */
    public function all(): array
    {
        return PlatformSetting::query()
            ->get()
            ->mapWithKeys(fn (PlatformSetting $s) => [
                $s->key => $this->cast($s->value, $s->type),
            ])
            ->all();
    }

    /**
     * Cast a stored text value to its PHP type.
     */
    private function cast(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            PlatformSetting::TYPE_BOOLEAN => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            PlatformSetting::TYPE_INTEGER => (int) $value,
            PlatformSetting::TYPE_JSON => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Serialise a PHP value to text for storage. Null stays null.
     */
    private function serialise(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            PlatformSetting::TYPE_BOOLEAN => $value ? '1' : '0',
            PlatformSetting::TYPE_INTEGER => (string) (int) $value,
            PlatformSetting::TYPE_JSON => json_encode($value),
            default => (string) $value,
        };
    }
}
