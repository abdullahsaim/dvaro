<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Contracts\CaptchaVerifierInterface;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Services\LeadFormService;
use App\Modules\CRM\Services\LeadFormSubmissionService;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The tenant's PUBLIC lead form — share link / QR (show) and website iframe
 * (embed). No auth, no TenantMiddleware: the tenant is resolved from
 * {tenant_slug} + the secret {token} (LeadFormService::resolvePublic) and bound
 * only for the submission write.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * NO SESSION / NO CSRF — READ BEFORE EDITING:
 * The embed runs in a CROSS-SITE iframe on the tenant's website, where
 * browsers don't send DVARO's (SameSite) cookies. So the POST is CSRF-exempt
 * (bootstrap/app.php) and nothing here may rely on the session: validation
 * errors are rendered straight back (never redirect()->withErrors()), and
 * success renders the thank-you page directly. Abuse protection is reCAPTCHA
 * + honeypot + timing token + rate limits (throttle:lead-form).
 *
 * FRAMING: only the embed page relaxes frame-ancestors (tenant's allowed
 * origins, or any site). Every other response gets 'self' from SecurityHeaders.
 * ──────────────────────────────────────────────────────────────────────────
 */
class PublicLeadFormController extends Controller
{
    public function __construct(
        private readonly LeadFormService $forms,
        private readonly CaptchaVerifierInterface $captcha,
    ) {}

    public function show(Request $request, string $tenant_slug, string $token): Response
    {
        return $this->renderForm($request, $tenant_slug, $token, embedded: false);
    }

    public function embed(Request $request, string $tenant_slug, string $token): Response
    {
        return $this->renderForm($request, $tenant_slug, $token, embedded: true);
    }

    public function submit(
        Request $request,
        string $tenant_slug,
        string $token,
        LeadFormSubmissionService $submissions,
    ): Response {
        $embedded = $request->boolean('embedded');
        $tenant = $this->forms->resolvePublic($tenant_slug, $token);

        if ($tenant === null) {
            return $this->unavailable($request, null, $embedded);
        }

        app()->instance('current_tenant', $tenant);

        $result = $submissions->handle(
            $tenant,
            $request->all(),
            $request->ip(),
            $embedded,
            $request->input('ref'),
        );

        if ($result['status'] === LeadFormSubmissionService::RESULT_INVALID) {
            return $this->page($request, $tenant, $embedded, 'CRM/PublicLeadForm', [
                ...$this->formProps($tenant, $tenant_slug, $token, $embedded),
                'errors' => $result['errors'],
            ], 422);
        }

        // created OR spam — a bot gets the same "thanks" and learns nothing.
        return $this->page($request, $tenant, $embedded, 'CRM/PublicLeadFormSuccess', [
            'tenantName' => $tenant->name,
            'embedded' => $embedded,
        ]);
    }

    private function renderForm(Request $request, string $slug, string $token, bool $embedded): Response
    {
        $tenant = $this->forms->resolvePublic($slug, $token);

        if ($tenant === null) {
            return $this->unavailable($request, null, $embedded);
        }

        return $this->page($request, $tenant, $embedded, 'CRM/PublicLeadForm',
            $this->formProps($tenant, $slug, $token, $embedded));
    }

    /**
     * @return array<string, mixed>
     */
    private function formProps(Tenant $tenant, string $slug, string $token, bool $embedded): array
    {
        return [
            'tenantName' => $tenant->name,
            'intro' => $this->forms->settings($tenant)['intro'],
            'submitUrl' => route('crm.lead-form.submit', ['tenant_slug' => $slug, 'token' => $token]),
            'started' => $this->forms->issueStartToken(),
            'siteKey' => $this->captcha->siteKey(),
            'embedded' => $embedded,
            'honeypot' => LeadFormSubmissionService::HONEYPOT_FIELD,
        ];
    }

    private function unavailable(Request $request, ?Tenant $tenant, bool $embedded): Response
    {
        return $this->page($request, $tenant, $embedded, 'CRM/PublicLeadFormUnavailable', [
            'embedded' => $embedded,
        ], 404);
    }

    /**
     * Render an Inertia page; embed responses carry the tenant's framing policy
     * (SecurityHeaders leaves an explicit CSP untouched).
     *
     * @param  array<string, mixed>  $props
     */
    private function page(Request $request, ?Tenant $tenant, bool $embedded, string $component, array $props, int $status = 200): Response
    {
        $response = Inertia::render($component, $props)->toResponse($request)->setStatusCode($status);

        if ($embedded) {
            $response->headers->set(
                'Content-Security-Policy',
                'frame-ancestors '.$this->forms->frameAncestors($tenant),
            );
        }

        return $response;
    }
}
