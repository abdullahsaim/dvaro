<?php

namespace App\Modules\Workshop\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\Workshop\Http\Controllers\MechanicAuthController;
use App\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * PUBLIC QR scan landing — the URL encoded in every vehicle's QR sticker.
 *
 * This route lives in web.php with NO mechanic.tenant middleware, so the tenant
 * is NOT bound on entry. We resolve it from the slug and bind it ourselves —
 * mandatory before either the scope-free vehicle lookup OR the mechanic-guard
 * check (the Mechanic model is tenant-scoped; a guard check with no bound tenant
 * would throw). The vehicle is looked up scope-free + explicit tenant_id, the
 * same safe pattern as the public CRM intake form.
 *
 * SIGNATURE: the QR encodes a Laravel signed URL (GenerateVehicleQrAction). We
 * verify it before anything else:
 *   - valid signature        → proceed;
 *   - signature present but   → TAMPERED → 403;
 *     invalid
 *   - no signature at all     → a LEGACY (pre-signing) sticker → a friendly
 *                               "expired, contact your rental company" page
 *                               (410), never a raw error.
 *
 * No tenant data is exposed: an authenticated mechanic is sent straight to the
 * vehicle's service page; a guest is sent to the mechanic login with the token
 * stashed so they return to this vehicle after signing in.
 */
class QrScanController extends Controller
{
    public function scan(Request $request, string $tenant_slug, string $token): Response
    {
        if (! $request->hasValidSignature()) {
            // A legacy sticker carries no `signature` param at all — treat it as
            // an expired code with a friendly message. A present-but-wrong
            // signature is a tampering attempt → hard 403.
            if ($request->query('signature') === null) {
                return Inertia::render('Workshop/QrExpired')
                    ->toResponse($request)
                    ->setStatusCode(410);
            }

            abort(403);
        }

        $tenant = Tenant::where('slug', $tenant_slug)->first();

        if ($tenant === null) {
            abort(404);
        }

        // Bind for the scope-free lookup below and the mechanic guard check.
        app()->instance('current_tenant', $tenant);

        $vehicle = Vehicle::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('qr_code_token', $token)
            ->first();

        if ($vehicle === null) {
            abort(404);
        }

        // Already signed in (to THIS tenant) → straight to the vehicle.
        if (auth('mechanic')->check()) {
            return redirect()->route('mechanic.vehicle', [
                'tenant_slug' => $tenant->slug,
                'token' => $token,
            ]);
        }

        // Otherwise remember where they were headed and send them to login.
        $request->session()->put(MechanicAuthController::INTENDED_TOKEN_KEY, $token);

        return redirect()->route('mechanic.login', ['tenant_slug' => $tenant->slug]);
    }
}
