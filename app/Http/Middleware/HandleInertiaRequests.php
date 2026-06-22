<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Registers Inertia's server-side request handling on the 'web' group.
 *
 * The critical job here is parent::share(), which exposes the session-flashed
 * validation 'errors' bag to every Inertia page. Without this middleware,
 * ValidationExceptions redirect back correctly but the errors are never shared
 * into page props, so form.errors stays empty and nothing renders.
 *
 * Per-request 'tenant' and 'auth' props are shared from TenantMiddleware via
 * Inertia::share(); they are intentionally NOT duplicated here.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request), // includes the validation 'errors' bag

            // One-shot flash messages (e.g. redirect()->with('success', ...)).
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Vehicle-change proration preview (step 1 of the two-step change
                // flow): the controller flashes the calculated split here so the
                // Agreement page can show it before the admin confirms. No writes
                // happen on preview.
                'proration_preview' => fn () => $request->session()->get('proration_preview'),
            ],
        ];
    }
}
