<?php

/**
 * One-off favicon generator — draws the DVARO "D" monogram (ink-900 #18181b
 * rounded-square background, white geometric D glyph echoing the wordmark's
 * open-D shape) with GD and emits every standard favicon asset. Run once:
 *   php scripts/generate-favicon.php
 * Safe to delete after running; not part of the request lifecycle.
 */

function drawIcon(int $size): GdImage
{
    $im = imagecreatetruecolor($size, $size);
    imagesavealpha($im, true);
    imagealphablending($im, true);
    $transparent = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im, 0, 0, $transparent);

    // Rounded-square background, ink-900 (#18181b).
    $bg = imagecolorallocate($im, 0x18, 0x18, 0x1b);
    $radius = (int) round($size * 0.22);
    imagefilledrectangle($im, $radius, 0, $size - $radius - 1, $size - 1, $bg);
    imagefilledrectangle($im, 0, $radius, $size - 1, $size - $radius - 1, $bg);
    foreach ([[0, 0], [1, 0], [0, 1], [1, 1]] as [$cx, $cy]) {
        $x = $cx === 0 ? $radius : $size - $radius - 1;
        $y = $cy === 0 ? $radius : $size - $radius - 1;
        imagefilledellipse($im, $x, $y, $radius * 2, $radius * 2, $bg);
    }

    // White open-"D" glyph: an outer rounded bowl with the inner cut away,
    // open on the left — mirrors the wordmark's D (public/images/landing/logo-black.png).
    $white = imagecolorallocate($im, 255, 255, 255);
    $cx = (int) round($size * 0.46);
    $cy = (int) round($size / 2);
    $outerR = (int) round($size * 0.30);
    $innerR = (int) round($size * 0.155);

    imagesetthickness($im, 1);
    imagefilledellipse($im, $cx, $cy, $outerR * 2, $outerR * 2, $white);
    imagefilledellipse($im, $cx, $cy, $innerR * 2, $innerR * 2, $transparent);
    // Re-fill the inner hole with the background colour (transparent ellipse
    // punches true alpha, which is what we want for the gap).
    imagefilledellipse($im, $cx, $cy, $innerR * 2, $innerR * 2, $bg);

    // Open the ring on the left with a background-coloured wedge, leaving a
    // "D"-like gap rather than a closed "O". Kept inside the ring's own
    // vertical span (never reaching the rounded-square corners above/below).
    $gapW = (int) round($size * 0.12);
    $gapTop = max($radius, $cy - $outerR + 2);
    $gapBottom = min($size - $radius, $cy + $outerR - 2);
    // Starts at x=0 (not $radius): safe because gapTop/gapBottom already stay
    // clear of the top/bottom corner-radius zones, so this never touches the
    // rounded corners — it just needs to reach the ring's own left edge.
    imagefilledrectangle($im, 0, $gapTop, $cx - $outerR + $gapW, $gapBottom, $bg);

    return $im;
}

function savePng(GdImage $im, string $path): void
{
    imagepng($im, $path);
    imagedestroy($im);
}

$sizes = [16, 32, 48, 180, 192, 512];
$out = __DIR__.'/../public';

foreach ($sizes as $size) {
    savePng(drawIcon($size), "{$out}/favicon-tmp-{$size}.png");
}

rename("{$out}/favicon-tmp-16.png", "{$out}/favicon-16x16.png");
rename("{$out}/favicon-tmp-32.png", "{$out}/favicon-32x32.png");
rename("{$out}/favicon-tmp-180.png", "{$out}/apple-touch-icon.png");
rename("{$out}/favicon-tmp-192.png", "{$out}/android-chrome-192x192.png");
rename("{$out}/favicon-tmp-512.png", "{$out}/android-chrome-512x512.png");

// Build favicon.ico containing the 16/32/48 PNGs (the modern "PNG-in-ICO"
// format, supported since Windows Vista / every current browser).
function buildIco(array $pngPaths, string $outPath): void
{
    $images = array_map(fn ($p) => ['data' => file_get_contents($p), 'size' => getimagesize($p)], $pngPaths);
    $count = count($images);

    $header = pack('vvv', 0, 1, $count);
    $dirEntries = '';
    $imageData = '';
    $offset = 6 + (16 * $count);

    foreach ($images as $img) {
        [$w, $h] = $img['size'];
        $len = strlen($img['data']);
        $dirEntries .= pack(
            'CCCCvvVV',
            $w >= 256 ? 0 : $w,
            $h >= 256 ? 0 : $h,
            0, 0, 1, 32,
            $len, $offset,
        );
        $imageData .= $img['data'];
        $offset += $len;
    }

    file_put_contents($outPath, $header.$dirEntries.$imageData);
}

buildIco([
    "{$out}/favicon-16x16.png",
    "{$out}/favicon-32x32.png",
    "{$out}/favicon-tmp-48.png",
], "{$out}/favicon.ico");

unlink("{$out}/favicon-tmp-48.png");

echo "Favicon assets written to public/.\n";
