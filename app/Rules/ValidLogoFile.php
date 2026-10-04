<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * A logo file is a real PNG/JPG/WEBP image, or a real SVG.
 *
 * Deliberately NOT Laravel's bare `mimes:png,jpg,jpeg,svg,webp` rule: that rule
 * trusts PHP's fileinfo (finfo) extension guess, and finfo is well known to
 * misdetect SVG — a genuine, valid SVG export from Illustrator/Figma/Inkscape
 * commonly comes back as `text/xml`, `text/plain` or `image/svg` depending on
 * the server's magic database, none of which match Laravel's expected
 * `image/svg+xml` for the `svg` extension. The upload then fails validation
 * with no useful explanation — exactly the kind of "it just gives an error"
 * report that is hardest to diagnose without a log line.
 *
 * Raster formats still get a real content check (finfo is reliable for those).
 * SVG gets an extension + lightweight content sniff instead (the file must
 * actually start like XML/SVG, so an arbitrary file renamed to .svg is still
 * rejected) rather than trusting a single unreliable MIME guess.
 */
class ValidLogoFile implements ValidationRule
{
    private const RASTER_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail(__('validation.file'));

            return;
        }

        $extension = strtolower((string) $value->getClientOriginalExtension());

        if ($extension === 'svg') {
            $this->validateSvg($value, $fail);

            return;
        }

        if (! in_array($extension, self::RASTER_EXTENSIONS, true)) {
            $fail(__('validation.mimes', ['values' => 'png, jpg, jpeg, svg, webp']));

            return;
        }

        $mime = (string) $value->getMimeType();

        if (! str_starts_with($mime, 'image/')) {
            $fail(__('validation.mimes', ['values' => 'png, jpg, jpeg, svg, webp']));
        }
    }

    private function validateSvg(UploadedFile $file, Closure $fail): void
    {
        $head = @file_get_contents($file->getRealPath(), false, null, 0, 1024);

        if ($head === false || ! (str_contains($head, '<svg') || str_contains($head, '<?xml'))) {
            $fail(__('validation.mimes', ['values' => 'png, jpg, jpeg, svg, webp']));
        }
    }
}
