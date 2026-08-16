<?php

namespace App\Modules\CMS\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * CmsContentBlock — a single editable piece of landing-page content.
 *
 * PLATFORM-WIDE: deliberately NO HasTenant trait and NO TenantScope. The
 * landing website belongs to the platform, not to any tenant, and is edited
 * only by the super admin. Reads/writes go through CmsContentService, which
 * adds Redis caching; this model is the bare persistence layer.
 *
 * A block is one of three types:
 *   - text     plain string (e.g. a heading)
 *   - richtext HTML from the editor (e.g. an about-us body)
 *   - image    an S3 path stored in image_path (content stays null)
 */
class CmsContentBlock extends Model
{
    public const TYPE_TEXT = 'text';

    public const TYPE_RICHTEXT = 'richtext';

    public const TYPE_IMAGE = 'image';

    public const TYPES = [
        self::TYPE_TEXT,
        self::TYPE_RICHTEXT,
        self::TYPE_IMAGE,
    ];

    protected $fillable = [
        'key',
        'type',
        'content',
        'image_path',
        'section',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function isImage(): bool
    {
        return $this->type === self::TYPE_IMAGE;
    }

    /**
     * Public URL for an image block's stored object (public disk, served from
     * /storage/), or null when the block is not an image / has no image set.
     * Used by the public pages and the super admin editor's preview.
     *
     * ROOT-RELATIVE on purpose: Storage::disk('public')->url() prefixes
     * APP_URL, so a mismatched env (e.g. APP_URL=http://localhost while
     * browsing 127.0.0.1:8000 or the demo vhost) produced broken image links.
     * CMS images are always served same-origin from the /storage symlink, so
     * the host is dropped entirely.
     */
    public function imageUrl(): ?string
    {
        if (! $this->isImage() || $this->image_path === null) {
            return null;
        }

        return '/storage/'.ltrim($this->image_path, '/');
    }

    /**
     * The value to expose to the frontend: the image URL for image blocks,
     * otherwise the raw text/richtext content.
     */
    public function value(): ?string
    {
        return $this->isImage() ? $this->imageUrl() : $this->content;
    }
}
