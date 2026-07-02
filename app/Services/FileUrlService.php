<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Produces a short-lived, tamper-evident URL for a stored SENSITIVE file
 * (agreement/invoice PDFs, report exports) regardless of the active driver.
 *
 * Driver-agnostic by design — this is the ONE place the storage driver is
 * branched, so no caller ever hardcodes 's3'. It resolves the configured
 * default disk (config('filesystems.default')): 'local' out of the box, 's3'
 * once AWS credentials are supplied.
 *
 *  - s3    → Storage::disk('s3')->temporaryUrl() (native pre-signed URL).
 *  - local → the Laravel 11 local driver has `serve => true`, so temporaryUrl()
 *            returns a framework-signed, expiring URL served by the built-in
 *            local file route — the same short-lived-URL security model as S3,
 *            without any custom download controller.
 *
 * Public assets (QR codes, CMS images) do NOT use this — they live on the
 * public disk and are served directly from /storage/.
 */
class FileUrlService extends BaseService
{
    /**
     * A signed, expiring URL to the given path on the default (sensitive) disk.
     */
    public function temporaryUrl(string $path, int $minutes = 15): string
    {
        return Storage::disk(config('filesystems.default'))
            ->temporaryUrl($path, now()->addMinutes($minutes));
    }
}
