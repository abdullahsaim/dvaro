<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Events\LeadSubmitted;
use App\Modules\CRM\Http\Requests\IntakeFormRequest;
use App\Modules\CRM\Models\Lead;
use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PUBLIC, UNAUTHENTICATED intake form. Lives in the 'web' group with NO tenant
 * middleware and NO auth — the prospective customer fills this in themselves.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * TENANT CONTEXT — READ BEFORE EDITING:
 * Because TenantMiddleware does NOT run here, app('current_tenant') is NOT
 * bound. The Lead model uses HasTenant/TenantScope, which THROWS on any query
 * when no tenant is bound. So every lead lookup in this controller MUST:
 *   (1) resolve the Tenant explicitly from {tenant_slug}, and
 *   (2) query the Lead with TenantScope dropped + an explicit tenant_id filter.
 * Never use a bare Lead::query() / Lead::where() here.
 *
 * Access is gated entirely by the signed URL (tamper → 403) plus the lead's own
 * expiry (isExpired → 410). There is no policy — there is no authenticated user.
 * ──────────────────────────────────────────────────────────────────────────
 */
class IntakeFormController extends Controller
{
    public function show(Request $request, string $tenant_slug, string $token): Response
    {
        $lead = $this->resolveLead($request, $tenant_slug, $token);

        return Inertia::render('CRM/IntakeForm', [
            'tenantName' => $lead->tenant->name,
            // Post target carries its own valid signature so the submit endpoint's
            // signature check passes.
            'submitUrl' => URL::signedRoute('crm.intake.submit', [
                'tenant_slug' => $tenant_slug,
                'token' => $token,
            ]),
            // Prefill whatever staff already captured; the customer completes it.
            'lead' => [
                'name' => $lead->name,
                'email' => $lead->email,
                'phone' => $lead->phone,
                'address' => $lead->address,
                'licence_number' => $lead->licence_number,
                'emergency_contact_name' => $lead->emergency_contact_name,
                'emergency_contact_phone' => $lead->emergency_contact_phone,
                'rental_start_date' => optional($lead->rental_start_date)->format('Y-m-d'),
                'rental_duration' => $lead->rental_duration,
                'notes' => $lead->notes,
            ],
        ]);
    }

    public function submit(IntakeFormRequest $request, string $tenant_slug, string $token): Response
    {
        $lead = $this->resolveLead($request, $tenant_slug, $token);

        $lead->update([
            ...$request->validated(),
            'submitted_at' => now(),
        ]);

        LeadSubmitted::dispatch($lead);

        return Inertia::render('CRM/IntakeSuccess', [
            'tenantName' => $lead->tenant->name,
        ]);
    }

    /**
     * Validate the signature, resolve the tenant by slug, and fetch the lead
     * scope-free + explicitly tenant-filtered. Aborts 403 (tampered link),
     * 404 (no such lead for this tenant), or 410 (link expired).
     */
    private function resolveLead(Request $request, string $tenant_slug, string $token): Lead
    {
        abort_unless($request->hasValidSignature(), 403);

        // Tenant does NOT use HasTenant, so this is scope-safe.
        $tenant = Tenant::where('slug', $tenant_slug)->firstOrFail();

        $lead = Lead::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('token', $token)
            ->first();

        abort_if($lead === null, 404);
        abort_if($lead->isExpired(), 410);

        return $lead;
    }
}
