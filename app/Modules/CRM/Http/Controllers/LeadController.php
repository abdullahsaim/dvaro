<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CRM\Actions\ConvertLeadAction;
use App\Modules\CRM\Actions\CreateLeadAction;
use App\Modules\CRM\Actions\ExpireLeadAction;
use App\Modules\CRM\Actions\GenerateLeadLinkAction;
use App\Modules\CRM\DTOs\CreateLeadDTO;
use App\Modules\CRM\Http\Requests\StoreLeadRequest;
use App\Modules\CRM\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lead management (tenant app). Thin controller: validate → Action →
 * redirect/render. All business logic lives in Actions.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING (same rule as Fleet/Customer):
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
class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Lead::class);

        // ?status= must be a known status, else "no filter".
        $status = in_array($request->query('status'), Lead::STATUSES, true)
            ? $request->query('status')
            : null;

        $search = trim((string) $request->query('search', ''));

        $leads = Lead::query()
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Per-status tab counts in ONE grouped query (tenant-scoped via HasTenant).
        $counts = Lead::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('CRM/Lead/Index', [
            'leads' => $leads,
            'filters' => [
                'status' => $status,
                'search' => $search,
            ],
            'counts' => [
                'all' => (int) $counts->sum(),
                ...collect(Lead::STATUSES)
                    ->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])
                    ->all(),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Lead::class);

        return Inertia::render('CRM/Lead/Create');
    }

    public function store(StoreLeadRequest $request, CreateLeadAction $action): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Lead::class);

        ['link' => $link] = $action->execute(CreateLeadDTO::fromRequest($request));

        // Re-render Create with the generated link so the admin can copy it.
        // No sending happens yet (Notification session) — manual share for now.
        return Inertia::render('CRM/Lead/Create', [
            'generatedLink' => $link,
        ]);
    }

    public function show(Lead $lead, GenerateLeadLinkAction $generateLink): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $lead);

        return Inertia::render('CRM/Lead/Show', [
            'lead' => $lead,
            'link' => $generateLink->execute($lead),
            'isConvertible' => $lead->isConvertible(),
            'isExpired' => $lead->isExpired(),
        ]);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('delete', $lead);

        // Soft delete only — no hard deletes anywhere in this codebase.
        $lead->delete();

        return redirect()
            ->route('tenant.leads.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.crm.deleted'));
    }

    public function convert(Lead $lead, ConvertLeadAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('convert', $lead);

        // Throws LeadNotConvertibleException (422) if the lead isn't in a
        // convertible state — the transaction inside the action rolls back.
        $customer = $action->execute($lead);

        return redirect()
            ->route('tenant.customers.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'customer' => $customer->id,
            ])
            ->with('success', __('common.crm.converted'));
    }

    public function expire(Lead $lead, ExpireLeadAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('expire', $lead);

        $action->execute($lead);

        return back()->with('success', __('common.crm.expired'));
    }
}
