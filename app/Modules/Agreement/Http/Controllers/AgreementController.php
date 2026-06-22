<?php

namespace App\Modules\Agreement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\DTOs\CreateAgreementDTO;
use App\Modules\Agreement\Http\Requests\SignAgreementRequest;
use App\Modules\Agreement\Http\Requests\StoreAgreementRequest;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Agreement management (tenant app). Thin controller: validate → Service →
 * redirect/render. All business logic lives in AgreementService.
 *
 * Agreements are IMMUTABLE — there is intentionally no edit/update/destroy.
 * State changes happen only via sign (draft → signed) and createVersion (a NEW
 * row), each routed to its own endpoint.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING (same rule as Fleet/Customer/CRM):
 * Every action MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, $target);
 *
 * Do NOT use $this->authorize(): it resolves the DEFAULT (web) guard, which is
 * empty here — tenant users live on the 'tenant' guard, so the policy would run
 * against a null user and silently pass. Keep the forUser(auth('tenant')->user())
 * form on EVERY action.
 * ──────────────────────────────────────────────────────────────────────────
 */
class AgreementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Agreement::class);

        // ?status= / ?type= must be known values, else "no filter".
        $status = in_array($request->query('status'), $this->statuses(), true)
            ? $request->query('status')
            : null;

        $type = in_array($request->query('type'), Agreement::TYPES, true)
            ? $request->query('type')
            : null;

        $search = trim((string) $request->query('search', ''));

        $agreements = Agreement::query()
            ->with([
                'customer:id,name',
                'vehicle:id,registration_number',
            ])
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            // Customer name is NOT encrypted, so a LIKE join works here.
            ->when($search !== '', fn ($query) => $query->whereHas(
                'customer',
                fn ($q) => $q->where('name', 'like', "%{$search}%"),
            ))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Per-status tab counts in ONE grouped query (tenant-scoped via HasTenant).
        $counts = Agreement::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('Agreement/Index', [
            'agreements' => $agreements,
            'filters' => [
                'status' => $status,
                'type' => $type,
                'search' => $search,
            ],
            'statuses' => $this->statuses(),
            'types' => Agreement::TYPES,
            'counts' => [
                'all' => (int) $counts->sum(),
                ...collect($this->statuses())
                    ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])
                    ->all(),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Agreement::class);

        return Inertia::render('Agreement/Create', [
            // Only non-archived customers; name is all the form needs.
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name']),
            // Only AVAILABLE vehicles can be put on a new agreement.
            'vehicles' => Vehicle::query()
                ->where('status', Vehicle::STATUS_AVAILABLE)
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'make', 'model']),
            'types' => Agreement::TYPES,
            'billingCycles' => Agreement::BILLING_CYCLES,
        ]);
    }

    public function store(StoreAgreementRequest $request, AgreementService $service): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Agreement::class);

        $agreement = $service->create(CreateAgreementDTO::fromRequest($request));

        return redirect()
            ->route('tenant.agreements.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'agreement' => $agreement->id,
            ])
            ->with('success', __('common.agreement.created'));
    }

    public function show(Agreement $agreement): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $agreement);

        $agreement->load(['customer', 'vehicle']);

        return Inertia::render('Agreement/Show', [
            'agreement' => $agreement,
            // Whole version lineage (tree), newest first, for the history list.
            'versions' => $this->lineage($agreement)
                ->map(fn (Agreement $a) => [
                    'id' => $a->id,
                    'version' => $a->version,
                    'status' => $a->status,
                    'parent_agreement_id' => $a->parent_agreement_id,
                    'signed_at' => $a->signed_at,
                    'created_at' => $a->created_at,
                ])
                ->values(),
        ]);
    }

    public function sign(SignAgreementRequest $request, Agreement $agreement, AgreementService $service): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('sign', $agreement);

        // The request already guards draft-status (Inertia-friendly error). The
        // service re-guards as the authority and throws if state changed under us.
        $service->sign($agreement, $request->string('signature_data')->toString());

        return redirect()
            ->route('tenant.agreements.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'agreement' => $agreement->id,
            ])
            ->with('success', __('common.agreement.signed'));
    }

    public function createVersion(Agreement $agreement, AgreementService $service): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('createVersion', $agreement);

        // Same terms as the current agreement — the new version starts as a draft
        // that must be signed afresh. (Bypasses StoreAgreementRequest by design,
        // so an original past start_date never blocks re-versioning.)
        $newVersion = $service->createNewVersion(
            $agreement,
            CreateAgreementDTO::fromAgreement($agreement),
        );

        return redirect()
            ->route('tenant.agreements.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'agreement' => $newVersion->id,
            ])
            ->with('success', __('common.agreement.version_created'));
    }

    /**
     * Stream the stored agreement PDF from S3. Read-only; the PDF is produced
     * asynchronously by GenerateAgreementPdfJob after signing, so pdf_path may
     * still be null briefly (→ 404 until it lands).
     */
    public function downloadPdf(Agreement $agreement): StreamedResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $agreement);

        abort_if($agreement->pdf_path === null, 404);

        return Storage::disk('s3')->download(
            $agreement->pdf_path,
            "agreement-{$agreement->id}-v{$agreement->version}.pdf",
        );
    }

    /**
     * The full version lineage of an agreement (its whole family tree), ordered
     * by version descending. Walks up to the root, then collects all
     * descendants. Read-only display concern, tenant-scoped throughout.
     *
     * @return Collection<int, Agreement>
     */
    private function lineage(Agreement $agreement): Collection
    {
        $root = $agreement;
        while ($root->parent_agreement_id !== null && $root->parentAgreement !== null) {
            $root = $root->parentAgreement;
        }

        $all = collect([$root]);
        $queue = [$root];

        while ($queue !== []) {
            /** @var Agreement $node */
            $node = array_shift($queue);
            foreach ($node->versions as $child) {
                $all->push($child);
                $queue[] = $child;
            }
        }

        return $all->sortByDesc('version')->values();
    }

    /**
     * @return array<int, string>
     */
    private function statuses(): array
    {
        return [
            Agreement::STATUS_DRAFT,
            Agreement::STATUS_SIGNED,
            Agreement::STATUS_ACTIVE,
            Agreement::STATUS_COMPLETED,
            Agreement::STATUS_CANCELLED,
        ];
    }
}
