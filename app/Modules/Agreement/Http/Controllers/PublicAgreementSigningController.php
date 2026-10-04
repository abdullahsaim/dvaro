<?php

namespace App\Modules\Agreement\Http\Controllers;

use App\Modules\Agreement\Http\Requests\PublicSignAgreementRequest;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementService;
use App\Modules\Agreement\Services\AgreementSigningService;
use App\Services\PdfAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The customer's PUBLIC review-and-sign page — opened from the link emailed
 * or WhatsApped from the agreement page (AgreementController::sendForSigning).
 * No auth, no TenantMiddleware: the agreement is resolved from
 * {tenant_slug} + the secret {token} (AgreementSigningService::resolvePublic)
 * and bound only for the duration of this request.
 *
 * Unlike the public lead form, this is a normal top-level navigation from an
 * email/WhatsApp link — never a cross-site iframe — so the ordinary session +
 * CSRF protection applies with no exemption needed.
 *
 * The SAME link is used before and after signing: show() renders the review
 * form for a draft agreement, or a read-only "thank you, here's your copy"
 * state for anything already signed — it never 404s a link just because it
 * has already been used once.
 */
class PublicAgreementSigningController
{
    public function __construct(
        private readonly AgreementSigningService $signing,
    ) {}

    public function show(Request $request, string $tenant_slug, string $token): Response
    {
        $agreement = $this->signing->resolvePublic($tenant_slug, $token);

        if ($agreement === null) {
            return $this->unavailable($request);
        }

        app()->instance('current_tenant', $agreement->tenant);
        $agreement->load(['customer', 'vehicle']);

        if ($agreement->status === Agreement::STATUS_DRAFT) {
            return $this->page($request, 'Agreement/PublicSign', $this->reviewProps($agreement, $tenant_slug, $token));
        }

        return $this->page($request, 'Agreement/PublicSignThankYou', $this->signedProps($agreement, $tenant_slug, $token));
    }

    public function submit(
        PublicSignAgreementRequest $request,
        string $tenant_slug,
        string $token,
        AgreementService $service,
    ): Response {
        $agreement = $this->signing->resolvePublic($tenant_slug, $token);

        if ($agreement === null) {
            return $this->unavailable($request);
        }

        app()->instance('current_tenant', $agreement->tenant);

        if ($agreement->status !== Agreement::STATUS_DRAFT) {
            // Already signed (e.g. a second tab submitted first) — the normal
            // "thank you" state, not an error; nothing was lost.
            $agreement->load(['customer', 'vehicle']);

            return $this->page($request, 'Agreement/PublicSignThankYou', $this->signedProps($agreement, $tenant_slug, $token));
        }

        try {
            $signed = $service->sign($agreement, $request->string('signature_data')->toString());
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'signature_data' => __('agreement.not_signable'),
            ]);
        }

        // sign() dispatches AgreementSigned, and the listener that emails the
        // customer their copy binds/forgets current_tenant for ITS OWN work
        // (QueuedNotificationListener::bindTenant/forgetTenant). Under a sync
        // queue connection that listener runs INLINE, in this same container,
        // so its forgetTenant() clears the binding this request still needs —
        // app()->instance() has no stack, so "forget" means gone, not
        // "restored to the caller's value". In production the listener runs in
        // a separate queue-worker process with its own container, so this
        // never happens there; re-binding here costs nothing and removes the
        // dependency on that distinction.
        app()->instance('current_tenant', $signed->tenant);
        $signed->load(['customer', 'vehicle']);

        return $this->page($request, 'Agreement/PublicSignThankYou', $this->signedProps($signed, $tenant_slug, $token));
    }

    /**
     * Stream the signed PDF — the public equivalent of the staff-side
     * downloadPdf, gated by the same token rather than auth:tenant (the
     * customer has no login). Same PdfAvailability guard against a recorded
     * path whose file doesn't actually exist.
     */
    public function downloadPdf(string $tenant_slug, string $token): StreamedResponse
    {
        $agreement = $this->signing->resolvePublic($tenant_slug, $token);

        abort_if($agreement === null, 404);

        app()->instance('current_tenant', $agreement->tenant);

        abort_unless(
            $agreement->status !== Agreement::STATUS_DRAFT
                && app(PdfAvailability::class)->exists($agreement->pdf_path),
            404,
        );

        return Storage::disk(config('filesystems.default'))->download(
            $agreement->pdf_path,
            "agreement-{$agreement->id}-v{$agreement->version}.pdf",
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function reviewProps(Agreement $agreement, string $slug, string $token): array
    {
        return [
            'tenantName' => $agreement->tenant->name,
            'customerName' => $agreement->customer?->name,
            'vehicle' => $this->vehicleLabel($agreement),
            'rate' => (int) $agreement->rate,
            'billingCycle' => $agreement->billing_cycle,
            'bondAmount' => (int) $agreement->bond_amount,
            'startDate' => $agreement->start_date,
            'endDate' => $agreement->end_date,
            'terms' => $agreement->terms_html,
            'submitUrl' => route('agreement-signing.submit', ['tenant_slug' => $slug, 'token' => $token]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function signedProps(Agreement $agreement, string $slug, string $token): array
    {
        return [
            'tenantName' => $agreement->tenant->name,
            'customerName' => $agreement->customer?->name,
            'vehicle' => $this->vehicleLabel($agreement),
            'signedAt' => $agreement->signed_at,
            'pdfReady' => app(PdfAvailability::class)->exists($agreement->pdf_path),
            'downloadUrl' => route('agreement-signing.pdf', ['tenant_slug' => $slug, 'token' => $token]),
        ];
    }

    private function vehicleLabel(Agreement $agreement): ?string
    {
        $vehicle = $agreement->vehicle;

        if ($vehicle === null) {
            return null;
        }

        return trim("{$vehicle->make} {$vehicle->model}").' ('.$vehicle->registration_number.')';
    }

    private function unavailable(Request $request): Response
    {
        return $this->page($request, 'Agreement/PublicSignUnavailable', [], 404);
    }

    /**
     * @param  array<string, mixed>  $props
     */
    private function page(Request $request, string $component, array $props, int $status = 200): Response
    {
        return Inertia::render($component, $props)->toResponse($request)->setStatusCode($status);
    }
}
