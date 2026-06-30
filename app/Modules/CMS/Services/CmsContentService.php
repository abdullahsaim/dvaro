<?php

namespace App\Modules\CMS\Services;

use App\Modules\CMS\Models\CmsContentBlock;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Read/write access to landing-page CMS content (the cms_content_blocks table).
 *
 * Cache-first by design: landing content changes rarely but is read on every
 * public page hit, so reads are served from Redis (1h TTL) and the DB is only
 * touched to warm a cold key. Every write (text or image) busts the affected
 * caches IMMEDIATELY so an edit reflects on the next page load — no waiting for
 * the TTL to lapse.
 *
 * Mirrors the cache-first pattern of PlatformSettingsService: a genuine "missing
 * key" is cached as a sentinel so a miss does not re-query on every call.
 *
 * CMS content is platform-wide (no tenant) — used by the public site and the
 * super admin CMS editor only.
 */
class CmsContentService
{
    private const CACHE_PREFIX = 'cms:';

    private const SECTION_PREFIX = 'cms:section:';

    /**
     * Content rarely changes and every write busts its key explicitly, so a
     * long TTL is safe; it mainly bounds drift if a row is edited directly.
     */
    private const CACHE_TTL = 3600; // 1h

    /**
     * Resolve a single block's display value by key. Cache-first.
     *
     * Returns the text/richtext content, or for an image block the public S3
     * URL. Falls back to $default when the key does not exist.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        $value = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key) {
            $block = CmsContentBlock::query()->where('key', $key)->first();

            if ($block === null) {
                return ['__missing__' => true];
            }

            return ['value' => $block->value()];
        });

        if (isset($value['__missing__'])) {
            return $default;
        }

        return $value['value'] ?? $default;
    }

    /**
     * All blocks for a section as a key => value map (display values), ordered
     * by sort_order. Cached as a unit per section.
     */
    public function getSection(string $section): Collection
    {
        $cacheKey = self::SECTION_PREFIX.$section;

        $rows = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($section) {
            return CmsContentBlock::query()
                ->where('section', $section)
                ->orderBy('sort_order')
                ->get()
                ->map(fn (CmsContentBlock $b) => [
                    'key' => $b->key,
                    'type' => $b->type,
                    'value' => $b->value(),
                ])
                ->all();
        });

        return collect($rows);
    }

    /**
     * Update a text/richtext block's content and bust its caches.
     *
     * The block must already exist (seeded by CmsContentSeeder). Image blocks
     * are updated via updateImage() instead.
     */
    public function update(string $key, mixed $value): void
    {
        $block = CmsContentBlock::query()->where('key', $key)->first();

        if ($block === null) {
            return;
        }

        $block->content = $value;
        $block->save();

        $this->forget($block);
    }

    /**
     * Store an uploaded image for an image block on S3 and bust its caches.
     *
     * The object lives at cms/images/{key}.{ext}; re-uploading replaces it
     * (deterministic path keyed by the block, so old files are not orphaned).
     */
    public function updateImage(string $key, UploadedFile $file): void
    {
        $block = CmsContentBlock::query()->where('key', $key)->first();

        if ($block === null) {
            return;
        }

        $path = "cms/images/{$key}.".$file->getClientOriginalExtension();
        Storage::disk('s3')->put($path, $file->get());

        $block->image_path = $path;
        $block->save();

        $this->forget($block);
    }

    /**
     * Bust both the per-key cache and the block's section cache so the next
     * read re-warms from the DB.
     */
    private function forget(CmsContentBlock $block): void
    {
        Cache::forget(self::CACHE_PREFIX.$block->key);
        Cache::forget(self::SECTION_PREFIX.$block->section);
    }
}
