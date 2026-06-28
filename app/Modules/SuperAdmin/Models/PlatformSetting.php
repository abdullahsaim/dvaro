<?php

namespace App\Modules\SuperAdmin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * PlatformSetting — a single platform-wide configuration key/value row.
 *
 * Platform-wide (NOT tenant-scoped). Reads/writes should go through
 * PlatformSettingsService, which casts `value` by `type` and caches.
 */
class PlatformSetting extends Model
{
    public const TYPE_STRING = 'string';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_INTEGER = 'integer';
    public const TYPE_JSON = 'json';

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
    ];
}
