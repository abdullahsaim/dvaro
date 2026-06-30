<?php

namespace App\Modules\Workshop\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Generates (or regenerates) a vehicle's QR code.
 *
 * The token is a deterministic HMAC of the vehicle id keyed by the app key, so
 * regenerating produces the SAME token (idempotent) and the printed QR sticker
 * never needs reprinting after a regenerate. The image is rendered as SVG
 * (dependency-free — no imagick/GD required, unlike PNG) and stored on S3.
 *
 * The QR encodes a Laravel SIGNED scan URL (mechanic.scan): the route is signed
 * with a `signature` query param computed (with APP_KEY) over the slug + token,
 * so QrScanController can reject a tampered URL (403). The signature is
 * PERMANENT (URL::signedRoute, NOT temporarySignedRoute) — a printed sticker
 * must not rot, and link lifetime is not a QR concern. The HMAC token is kept in
 * addition: it remains the DB lookup key for the vehicle.
 *
 * Scanning it routes a mechanic to login (then back to the vehicle) or straight
 * to the vehicle if already authenticated. No tenant data is embedded — just the
 * slug + token + signature.
 */
class GenerateVehicleQrAction extends BaseAction
{
    public function execute(Vehicle $vehicle): Vehicle
    {
        // Deterministic, unguessable token — keyed by APP_KEY, not derivable
        // without it. (string) cast: hash_hmac requires a string message.
        $token = hash_hmac('sha256', (string) $vehicle->id, config('app.key'));

        // tenant() is a plain belongsTo (Tenant is not tenant-scoped), so this
        // resolves the slug correctly even with no current_tenant bound.
        $slug = $vehicle->tenant->slug;

        // Permanent signed URL — tamper-evident, never expires (printed sticker).
        $url = URL::signedRoute('mechanic.scan', [
            'tenant_slug' => $slug,
            'token' => $token,
        ]);

        $svg = QrCode::format('svg')->size(320)->margin(1)->generate($url);

        Storage::disk('s3')->put($this->qrPath($vehicle), (string) $svg, [
            'ContentType' => 'image/svg+xml',
        ]);

        $vehicle->qr_code_token = $token;
        $vehicle->save();

        return $vehicle;
    }

    /**
     * Deterministic S3 path for a vehicle's QR image.
     */
    public static function qrPath(Vehicle $vehicle): string
    {
        return "tenants/{$vehicle->tenant_id}/vehicles/{$vehicle->id}/qr.svg";
    }
}
