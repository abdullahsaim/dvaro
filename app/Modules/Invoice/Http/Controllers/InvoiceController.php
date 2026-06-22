<?php

namespace App\Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateInvoicePdfJob;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Actions\RecordPaymentAction;
use App\Modules\Invoice\DTOs\RecordPaymentDTO;
use App\Modules\Invoice\Http\Requests\ChangeVehicleRequest;
use App\Modules\Invoice\Http\Requests\RecordPaymentRequest;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use App\Modules\Invoice\Services\ProrationService;
use App\Modules\Invoice\Services\VehicleChangeService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice management (tenant app). Thin controller: validate → Service/Action →
 * redirect/render. All business logic lives in the Invoice services/actions.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING (same rule as Fleet/Customer/Agreement):
 * Every action MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, $target);
 *
 * Do NOT use $this->authorize(): it resolves the DEFAULT (web) guard, which is
 * empty here — tenant users live on the 'tenant' guard, so the policy would run
 * against a null user and silently pass.
 * ──────────────────────────────────────────────────────────────────────────
 */
class InvoiceController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Invoice::class);

        $status = in_array($request->query('status'), Invoice::STATUSES, true)
            ? $request->query('status')
            : null;

        $search = trim((string) $request->query('search', ''));

        $invoices = Invoice::query()
            ->with(['customer:id,name'])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            // Customer name is NOT encrypted, so a LIKE join works here.
            ->when($search !== '', fn ($query) => $query->whereHas(
                'customer',
                fn ($q) => $q->where('name', 'like', "%{$search}%"),
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Per-status tab counts in ONE grouped query (tenant-scoped via HasTenant).
        $counts = Invoice::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Invoice/Index', [
            'invoices' => $invoices,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'statuses' => Invoice::STATUSES,
            'counts' => [
                'all' => (int) $counts->sum(),
                ...collect(Invoice::STATUSES)
                    ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])
                    ->all(),
            ],
        ]);
    }

    public function show(Invoice $invoice, LedgerService $ledger): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $invoice);

        $invoice->load([
            'customer:id,name',
            'items.vehicle:id,registration_number',
            'payments.recordedBy:id,name',
            'agreement:id,version',
        ]);

        return Inertia::render('Invoice/Show', [
            'invoice' => $invoice,
            // The customer's whole-ledger balance, for context on this invoice.
            'customerBalance' => $ledger->getBalance($invoice->customer_id),
            'methods' => Payment::METHODS,
        ]);
    }

    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice, RecordPaymentAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('recordPayment', $invoice);

        $action->execute(RecordPaymentDTO::fromRequest($request, $invoice->id, (int) $invoice->customer_id));

        // Regenerate the PDF so the stored copy reflects the new payment. Queued.
        GenerateInvoicePdfJob::dispatch($invoice->id, $invoice->tenant_id);

        return back()->with('success', __('common.invoice.payment_recorded'));
    }

    /**
     * Manual override: flag an invoice overdue. Never overrides a paid or
     * cancelled invoice (those are terminal for this purpose).
     */
    public function markOverdue(Invoice $invoice): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('markOverdue', $invoice);

        if (! in_array($invoice->status, [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED], true)) {
            $invoice->update(['status' => Invoice::STATUS_OVERDUE]);
        }

        return back()->with('success', __('common.invoice.marked_overdue'));
    }

    /**
     * Two-step mid-cycle vehicle change.
     *
     * PREVIEW (confirm absent/false): calls ProrationService::calculate() ONLY —
     * a pure read, NO writes. The split is flashed back to the Agreement page for
     * the admin to review.
     *
     * CONFIRM (confirm=true): calls VehicleChangeService::execute(), which voids
     * the open invoice, raises the two prorated invoices, versions the agreement,
     * and moves vehicle statuses — all atomically.
     *
     * Authorized on the AGREEMENT (createVersion ability) since the change
     * produces a new agreement version.
     */
    public function changeVehicle(
        ChangeVehicleRequest $request,
        Agreement $agreement,
        ProrationService $proration,
        VehicleChangeService $service,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('createVersion', $agreement);

        $newVehicle = Vehicle::findOrFail($request->integer('new_vehicle_id'));
        $changeDate = Carbon::parse($request->string('change_date')->toString());

        if (! $request->isConfirmed()) {
            // ── PREVIEW: pure calculation, NO database writes. ──
            try {
                $split = $proration->calculate($agreement, $changeDate, $newVehicle);
            } catch (RuntimeException $e) {
                return back()->with('error', $e->getMessage());
            }

            return back()->with('proration_preview', [
                'agreement_id' => $agreement->id,
                'new_vehicle_id' => $newVehicle->id,
                'new_vehicle_label' => $newVehicle->registration_number,
                'change_date' => $changeDate->toDateString(),
                ...$split,
            ]);
        }

        // ── CONFIRM: perform the change atomically. ──
        try {
            $result = $service->execute($agreement, $newVehicle, $changeDate);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('tenant.agreements.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'agreement' => $result['new_version']->id,
            ])
            ->with('success', __('common.invoice.vehicle_changed'));
    }

    /**
     * Stream the stored invoice PDF from S3. Read-only; the PDF is produced
     * asynchronously by GenerateInvoicePdfJob, so pdf_path may be null briefly.
     */
    public function downloadPdf(Invoice $invoice): StreamedResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $invoice);

        abort_if($invoice->pdf_path === null, 404);

        return Storage::disk('s3')->download(
            $invoice->pdf_path,
            "invoice-{$invoice->id}.pdf",
        );
    }
}
