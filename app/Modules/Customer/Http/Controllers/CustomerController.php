<?php

namespace App\Modules\Customer\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Customer\Actions\BlacklistCustomerAction;
use App\Modules\Customer\Actions\CreateCustomerAction;
use App\Modules\Customer\Actions\UnblacklistCustomerAction;
use App\Modules\Customer\Actions\UpdateCustomerAction;
use App\Modules\Customer\DTOs\CreateCustomerDTO;
use App\Modules\Customer\DTOs\UpdateCustomerDTO;
use App\Modules\Customer\Http\Requests\BlacklistRequest;
use App\Modules\Customer\Http\Requests\StoreCustomerRequest;
use App\Modules\Customer\Http\Requests\UpdateCustomerRequest;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer management (tenant app). Thin controller: validate → Action →
 * redirect/render. All business logic lives in Actions.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — READ BEFORE EDITING:
 * Every action that touches a SPECIFIC customer (show, edit, update, destroy,
 * blacklist, unblacklist) MUST authorize via:
 *
 *     Gate::forUser(auth('tenant')->user())->authorize($ability, $customer);
 *
 * (Laravel ships no global gate() helper — use the Gate facade. The
 * forUser(auth('tenant')->user()) part is what matters.) Do NOT use
 * $this->authorize(): it resolves the user from the DEFAULT (web) guard, which
 * is empty here — tenant users live on the 'tenant' guard. Falling back to
 * $this->authorize() on even one action silently runs the policy check against
 * the wrong (null) user, so CustomerPolicy never actually runs against the
 * tenant user. Keep the forUser(auth('tenant')->user()) form on EVERY one.
 * ──────────────────────────────────────────────────────────────────────────
 */
class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Customer::class);

        $search = trim((string) $request->query('search', ''));

        // ?blacklisted=true|false. Anything else means "no filter".
        $blacklisted = match ($request->query('blacklisted')) {
            'true' => true,
            'false' => false,
            default => null,
        };

        $customers = Customer::query()
            // email is encrypted at rest, so a SQL LIKE can't match it — search
            // is scoped to name + phone (plaintext columns) by design.
            ->when($search !== '', fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->when($blacklisted !== null, fn ($query) => $query->where('is_blacklisted', $blacklisted))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Outstanding balance indicator for the rows on this page, in ONE query
        // (grouped sum) instead of N per-row lookups. Tenant-scoped via HasTenant.
        $ids = collect($customers->items())->pluck('id');
        $balances = LedgerEntry::query()
            ->whereIn('customer_id', $ids)
            ->selectRaw('customer_id, SUM(amount) as balance')
            ->groupBy('customer_id')
            ->pluck('balance', 'customer_id');

        $customers->through(fn (Customer $customer) => tap($customer, function (Customer $c) use ($balances) {
            $c->setAttribute('outstanding_balance', (int) ($balances[$c->id] ?? 0));
        }));

        return Inertia::render('Customer/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
                'blacklisted' => $request->query('blacklisted'),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Customer::class);

        return Inertia::render('Customer/Create');
    }

    public function store(StoreCustomerRequest $request, CreateCustomerAction $action): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Customer::class);

        $action->execute(CreateCustomerDTO::fromRequest($request));

        return redirect()
            ->route('tenant.customers.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.customer.created'));
    }

    public function show(Customer $customer, LedgerService $ledger): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('view', $customer);

        return Inertia::render('Customer/Show', [
            'customer' => $customer,
            // Cents; positive = owes. Web request has current_tenant bound, so
            // LedgerService (TenantScope) is safe here.
            'outstandingBalance' => $ledger->getBalance($customer->id),
            // Rentals module not built yet — placeholder rendered by the page.
            'rentalHistory' => [],
        ]);
    }

    public function edit(Customer $customer): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('update', $customer);

        return Inertia::render('Customer/Edit', [
            'customer' => $customer,
        ]);
    }

    public function update(
        UpdateCustomerRequest $request,
        Customer $customer,
        UpdateCustomerAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('update', $customer);

        $action->execute($customer, UpdateCustomerDTO::fromRequest($request));

        return redirect()
            ->route('tenant.customers.show', [
                'tenant_slug' => app('current_tenant')->slug,
                'customer' => $customer->id,
            ])
            ->with('success', __('common.customer.updated'));
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('delete', $customer);

        // Soft delete only — no hard deletes anywhere in this codebase.
        $customer->delete();

        return redirect()
            ->route('tenant.customers.index', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.customer.deleted'));
    }

    public function blacklist(
        BlacklistRequest $request,
        Customer $customer,
        BlacklistCustomerAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('blacklist', $customer);

        $action->execute($customer, $request->string('reason')->toString());

        return back()->with('success', __('common.customer.blacklisted'));
    }

    public function unblacklist(
        Customer $customer,
        UnblacklistCustomerAction $action,
    ): RedirectResponse {
        Gate::forUser(auth('tenant')->user())->authorize('blacklist', $customer);

        $action->execute($customer);

        return back()->with('success', __('common.customer.unblacklisted'));
    }
}
