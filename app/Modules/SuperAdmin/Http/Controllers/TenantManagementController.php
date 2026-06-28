<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SuperAdmin\Events\TenantActivated;
use App\Modules\SuperAdmin\Events\TenantSuspended;
use App\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant management for the super admin panel (support tooling).
 *
 * ──────────────────────────────────────────────────────────────────────────
 * AUTHORIZATION — every action authorizes via:
 *
 *     Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');
 *
 * NOT $this->authorize() (which resolves the empty default/web guard). The
 * super admin lives on the 'superadmin' guard.
 *
 * TENANT SCOPING — there is no bound tenant in the super admin panel, so every
 * query on a tenant-scoped model (TenantUser, Vehicle, Invoice, Subscription)
 * drops TenantScope and constrains by tenant_id explicitly (via the Tenant
 * relations, which already drop the scope).
 *
 * IMPERSONATION is session-only and never writes the DB: log into the tenant
 * guard as the tenant's first admin and stamp a session marker. See impersonate().
 * ──────────────────────────────────────────────────────────────────────────
 */
class TenantManagementController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        $status = $request->query('status');
        $statuses = [Tenant::STATUS_ACTIVE, Tenant::STATUS_TRIAL, Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED];
        if (! in_array($status, $statuses, true)) {
            $status = null;
        }

        $search = trim((string) $request->query('search', ''));

        $tenants = Tenant::query()
            ->with('activeSubscription.plan:id,name')
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%")
                        // Match by an admin/staff email belonging to the tenant.
                        ->orWhereHas('users', fn ($u) => $u->where('email', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Tenant $tenant) => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'plan_name' => $tenant->activeSubscription?->plan?->name,
                'created_at' => $tenant->created_at,
            ]);

        $counts = Tenant::query()
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $statusCounts = ['all' => (int) $counts->sum()];
        foreach ($statuses as $s) {
            $statusCounts[$s] = (int) ($counts[$s] ?? 0);
        }

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'statuses' => $statuses,
            'statusCounts' => $statusCounts,
            'activeStatus' => $status,
            'search' => $search,
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        $subscriptions = $tenant->subscriptions()->with('plan:id,name,price_monthly,price_annual')->get();

        return Inertia::render('SuperAdmin/Tenants/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'trial_ends_at' => $tenant->trial_ends_at,
                'created_at' => $tenant->created_at,
            ],
            'counts' => [
                'users' => $tenant->users()->count(),
                'vehicles' => Vehicle::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenant->id)->count(),
                'invoices' => Invoice::withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenant->id)->count(),
            ],
            'subscriptions' => $subscriptions->map(fn ($sub) => [
                'id' => $sub->id,
                'status' => $sub->status,
                'billing_cycle' => $sub->billing_cycle,
                'plan_name' => $sub->plan?->name,
                'current_period_start' => $sub->current_period_start,
                'current_period_end' => $sub->current_period_end,
                'trial_ends_at' => $sub->trial_ends_at,
                'created_at' => $sub->created_at,
            ]),
        ]);
    }

    public function suspend(Tenant $tenant): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        if ($tenant->status !== Tenant::STATUS_SUSPENDED) {
            $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);
            TenantSuspended::dispatch($tenant);
        }

        return back()->with('success', __('common.superadmin.tenant_suspended'));
    }

    public function activate(Tenant $tenant): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('supportAccess');

        if ($tenant->status !== Tenant::STATUS_ACTIVE) {
            $tenant->update(['status' => Tenant::STATUS_ACTIVE]);
            TenantActivated::dispatch($tenant);
        }

        return back()->with('success', __('common.superadmin.tenant_activated'));
    }

    /**
     * Impersonate a tenant — SESSION ONLY, no DB writes.
     *
     * Logs into the tenant guard as the tenant's first admin user and stamps the
     * impersonator's super admin id in the session so the tenant UI can show a
     * banner (CheckImpersonation) and stopImpersonating() can unwind it.
     */
    public function impersonate(Request $request, Tenant $tenant): RedirectResponse
    {
        $admin = auth('superadmin')->user();
        Gate::forUser($admin)->authorize('supportAccess');

        $tenantAdmin = $tenant->users()
            ->where('role', TenantUser::ROLE_ADMIN)
            ->oldest()
            ->first();

        if ($tenantAdmin === null) {
            return back()->with('error', __('common.superadmin.no_admin_to_impersonate'));
        }

        // Log into the tenant guard as that admin (session-only). The superadmin
        // guard session is left intact so stopImpersonating() can return here.
        Auth::guard('tenant')->login($tenantAdmin);
        $request->session()->put('impersonator_superadmin_id', $admin->id);

        return redirect()->route('tenant.dashboard', ['tenant_slug' => $tenant->slug]);
    }

    /**
     * Stop impersonating. Per requirement, clears BOTH pieces of state in one
     * place so it can never become inconsistent:
     *   1. logs out the tenant guard
     *   2. removes the impersonation marker from the session
     */
    public function stopImpersonating(Request $request): RedirectResponse
    {
        Auth::guard('tenant')->logout();
        $request->session()->forget('impersonator_superadmin_id');

        return redirect()->route('superadmin.dashboard')
            ->with('success', __('common.superadmin.impersonation_stopped'));
    }
}
