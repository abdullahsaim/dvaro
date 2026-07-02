<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Http\Requests\AcceptInvitationRequest;
use App\Modules\Customer\Http\Requests\MakePaymentRequest;
use App\Modules\Customer\Models\CustomerPortalInvitation;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Invoice\Actions\RecordPaymentAction;
use App\Modules\Invoice\DTOs\RecordPaymentDTO;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\FileUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer Portal — the end-customer (car renter) area, served under the
 * 'customer' guard. Two surfaces live here:
 *
 *   - PUBLIC invitation acceptance (showAcceptInvitation / acceptInvitation):
 *     reached from web.php with NO customer.tenant middleware, so the tenant is
 *     NOT bound on entry — these methods resolve + bind it from {tenant_slug}
 *     themselves and look the invitation up scope-free by token AND explicit
 *     tenant_id (a token from tenant A can never be redeemed on tenant B).
 *
 *   - AUTHENTICATED portal (dashboard / invoices / agreements / payment):
 *     behind auth:customer; the tenant is already bound by ResolveTenantForCustomer.
 *
 * EVERY authenticated action is scoped to the logged-in customer's OWN
 * customer_id — never another customer's data, even within the same tenant.
 * Authorization for individual records goes through
 * Gate::forUser(auth('customer')->user())->authorize(...) — the load-bearing
 * forUser pattern used across the app; $this->authorize() would resolve the
 * empty web guard and silently skip the check.
 */
class CustomerPortalController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledger,
    ) {}

    // ----------------------------------------------------------------------
    // PUBLIC — invitation acceptance (no bound tenant on entry)
    // ----------------------------------------------------------------------

    public function showAcceptInvitation(string $tenant_slug, string $token): Response
    {
        $tenant = $this->resolveTenant($tenant_slug);
        $invitation = $this->findValidInvitation($tenant, $token);

        return Inertia::render('Customer/Portal/AcceptInvitation', [
            'tenantSlug' => $tenant->slug,
            'tenantName' => $tenant->name,
            'token' => $token,
            'email' => $invitation->email,
        ]);
    }

    public function acceptInvitation(AcceptInvitationRequest $request, string $tenant_slug, string $token): RedirectResponse
    {
        $tenant = $this->resolveTenant($tenant_slug);
        $invitation = $this->findValidInvitation($tenant, $token);

        $user = DB::transaction(function () use ($request, $tenant, $invitation): CustomerUser {
            $user = CustomerUser::create([
                'tenant_id' => $tenant->id,
                'customer_id' => $invitation->customer_id,
                'email' => $invitation->email,
                'password' => $request->input('password'), // hashed cast
            ]);

            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::guard('customer')->login($user);
        $request->session()->regenerate();

        return redirect()->route('customer.dashboard', ['tenant_slug' => $tenant->slug]);
    }

    // ----------------------------------------------------------------------
    // AUTHENTICATED — portal (tenant bound by ResolveTenantForCustomer)
    // ----------------------------------------------------------------------

    public function dashboard(): Response
    {
        $customerId = $this->customerId();

        // Current rental status: the newest signed/active agreement, latest
        // version only (no newer version points back at it).
        $currentRental = Agreement::with('vehicle')
            ->where('customer_id', $customerId)
            ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
            ->orderByDesc('id')
            ->get()
            ->first(fn (Agreement $a) => $a->latestVersion());

        $recentInvoices = Invoice::where('customer_id', $customerId)
            ->orderByDesc('id')
            ->take(3)
            ->get();

        return Inertia::render('Customer/Portal/Dashboard', [
            'outstandingBalance' => $this->ledger->getBalance($customerId),
            'currentRental' => $currentRental ? [
                'id' => $currentRental->id,
                'status' => $currentRental->status,
                'vehicle' => $currentRental->vehicle?->only(['id', 'make', 'model', 'registration_number']),
                'start_date' => $currentRental->start_date?->toDateString(),
                'end_date' => $currentRental->end_date?->toDateString(),
            ] : null,
            'recentInvoices' => $recentInvoices->map(fn (Invoice $i) => $this->invoiceSummary($i)),
        ]);
    }

    public function invoices(): Response
    {
        $invoices = Invoice::where('customer_id', $this->customerId())
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Invoice $i) => $this->invoiceSummary($i));

        return Inertia::render('Customer/Portal/Invoices/Index', [
            'invoices' => $invoices,
        ]);
    }

    public function showInvoice(Invoice $invoice): Response
    {
        Gate::forUser(auth('customer')->user())->authorize('viewPortalInvoice', $invoice);

        $invoice->load(['items.vehicle', 'payments.recordedBy', 'agreement']);

        return Inertia::render('Customer/Portal/Invoices/Show', [
            'invoice' => [
                ...$this->invoiceSummary($invoice),
                'subtotal' => $invoice->subtotal,
                'billing_period_start' => $invoice->billing_period_start?->toDateString(),
                'billing_period_end' => $invoice->billing_period_end?->toDateString(),
                'has_pdf' => $invoice->pdf_path !== null,
                'items' => $invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'amount' => $item->amount,
                    'vehicle' => $item->vehicle?->only(['id', 'make', 'model', 'registration_number']),
                    'period_start' => $item->period_start?->toDateString(),
                    'period_end' => $item->period_end?->toDateString(),
                ]),
                'payments' => $invoice->payments->map(fn ($p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'method' => $p->method,
                    'paid_at' => $p->paid_at?->toDateTimeString(),
                ]),
            ],
        ]);
    }

    public function downloadInvoicePdf(Invoice $invoice): RedirectResponse
    {
        Gate::forUser(auth('customer')->user())->authorize('viewPortalInvoice', $invoice);

        return $this->signedPdfRedirect($invoice->pdf_path);
    }

    public function agreements(): Response
    {
        $agreements = Agreement::with('vehicle')
            ->where('customer_id', $this->customerId())
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Agreement $a) => $this->agreementSummary($a));

        return Inertia::render('Customer/Portal/Agreements/Index', [
            'agreements' => $agreements,
        ]);
    }

    public function showAgreement(Agreement $agreement): Response
    {
        Gate::forUser(auth('customer')->user())->authorize('viewPortalAgreement', $agreement);

        $agreement->load('vehicle');

        return Inertia::render('Customer/Portal/Agreements/Show', [
            'agreement' => [
                ...$this->agreementSummary($agreement),
                'bond_amount' => $agreement->bond_amount,
                'billing_cycle' => $agreement->billing_cycle,
                'signed_at' => $agreement->signed_at?->toDateTimeString(),
                'has_pdf' => $agreement->pdf_path !== null,
            ],
        ]);
    }

    public function downloadAgreementPdf(Agreement $agreement): RedirectResponse
    {
        Gate::forUser(auth('customer')->user())->authorize('viewPortalAgreement', $agreement);

        return $this->signedPdfRedirect($agreement->pdf_path);
    }

    public function makePayment(MakePaymentRequest $request, Invoice $invoice, RecordPaymentAction $action): RedirectResponse
    {
        Gate::forUser(auth('customer')->user())->authorize('makePortalPayment', $invoice);

        // Capture the slug BEFORE running the action: PaymentReceived fans out to
        // a notification listener that binds + then forgets the tenant in a
        // finally block. Under a sync queue that runs inline here and would leave
        // current_tenant unbound for the redirect below.
        $slug = app('current_tenant')->slug;

        $action->execute(RecordPaymentDTO::fromRequest($request, $invoice->id, $invoice->customer_id));

        return redirect()
            ->route('customer.invoices.show', [
                'tenant_slug' => $slug,
                'invoice' => $invoice->id,
            ])
            ->with('success', __('common.customer.portal_payment_recorded'));
    }

    // ----------------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------------

    /** The authenticated customer's profile id — the single scoping key. */
    private function customerId(): int
    {
        return (int) auth('customer')->user()->customer_id;
    }

    private function resolveTenant(string $slug): Tenant
    {
        $tenant = Tenant::where('slug', $slug)->first();

        if ($tenant === null) {
            abort(404);
        }

        if (in_array($tenant->status, [Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED], true)) {
            abort(403, 'This tenant account is not active.');
        }

        // Bind so any scoped op below resolves correctly (mirrors QrScanController).
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    /**
     * REQUIREMENT: the public token lookup matches on BOTH token AND explicit
     * tenant_id — a token from tenant A must never work on tenant B's portal.
     * Then validate it is neither expired nor already accepted.
     */
    private function findValidInvitation(Tenant $tenant, string $token): CustomerPortalInvitation
    {
        $invitation = CustomerPortalInvitation::withoutGlobalScope(TenantScope::class)
            ->where('token', $token)
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($invitation === null) {
            abort(404);
        }

        if ($invitation->isAccepted()) {
            // Already redeemed — send them to log in instead.
            abort(410, __('common.customer.portal_invite_used'));
        }

        if ($invitation->isExpired()) {
            abort(410, __('common.customer.portal_invite_expired'));
        }

        return $invitation;
    }

    private function signedPdfRedirect(?string $path): RedirectResponse
    {
        if ($path === null) {
            abort(404);
        }

        // Short-lived signed URL (15 min), driver agnostic via FileUrlService —
        // local signed route or S3 pre-signed URL. Never a direct public path.
        $url = app(FileUrlService::class)->temporaryUrl($path);

        return redirect()->away($url);
    }

    /** @return array<string, mixed> */
    private function invoiceSummary(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'status' => $invoice->status,
            'type' => $invoice->type,
            'total' => $invoice->total,
            'paid_amount' => $invoice->paid_amount,
            'outstanding' => $invoice->outstandingAmount(),
            'due_date' => $invoice->due_date?->toDateString(),
        ];
    }

    /** @return array<string, mixed> */
    private function agreementSummary(Agreement $agreement): array
    {
        return [
            'id' => $agreement->id,
            'type' => $agreement->type,
            'status' => $agreement->status,
            'version' => $agreement->version,
            'rate' => $agreement->rate,
            'start_date' => $agreement->start_date?->toDateString(),
            'end_date' => $agreement->end_date?->toDateString(),
            'vehicle' => $agreement->vehicle?->only(['id', 'make', 'model', 'registration_number']),
        ];
    }
}
