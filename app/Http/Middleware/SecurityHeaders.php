<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Anti-clickjacking: no DVARO page may be framed by another site.
 *
 * Adds `Content-Security-Policy: frame-ancestors 'self'` + `X-Frame-Options:
 * SAMEORIGIN` to every web response — UNLESS the response already carries its
 * own Content-Security-Policy. The only route that does is the public lead-form
 * EMBED (PublicLeadFormController), which must be frameable by the tenant's
 * website; X-Frame-Options is omitted there because it can't express an
 * allow-list (and would override the CSP in older browsers).
 *
 * Framing policy only — this deliberately does not add a script/style CSP.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        return $response;
    }
}
