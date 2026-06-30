<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Http\Requests\FindWorkspaceRequest;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Global workspace lookup — the single front door for tenant staff who only know
 * their email, not their tenant slug. Tenant login is path-based
 * (/app/{slug}/login), so this resolves the slug from the email and forwards.
 *
 * PUBLIC, pre-tenant: no auth and NO tenant middleware — no tenant is bound here.
 * The TenantUser lookup is therefore scope-free (withoutGlobalScope) — a bare
 * query would throw TenantNotResolvedException — and matches across ALL tenants
 * by email. Suspended/cancelled tenants are excluded (their app would 403 the
 * login anyway). Outcomes:
 *   - 0 matches → render the page with notFound + a "register?" prompt;
 *   - 1 match   → redirect straight to that tenant's login;
 *   - 2+ matches → render the page with a workspace picker (one email used at
 *     several tenants).
 */
class WorkspaceLookupController extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Public/FindWorkspace');
    }

    public function find(FindWorkspaceRequest $request): Response|RedirectResponse
    {
        $email = Str::lower($request->string('email')->toString());

        // Scope-free, cross-tenant lookup (no tenant bound on this public route).
        $workspaces = TenantUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->with('tenant:id,slug,name,status')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->get()
            ->map(fn (TenantUser $user) => $user->tenant)
            ->filter(fn (?Tenant $tenant) => $tenant !== null
                && ! in_array($tenant->status, [Tenant::STATUS_SUSPENDED, Tenant::STATUS_CANCELLED], true))
            ->unique('id')
            ->map(fn (Tenant $tenant) => [
                'slug' => $tenant->slug,
                'name' => $tenant->name,
            ])
            ->values();

        if ($workspaces->isEmpty()) {
            return Inertia::render('Public/FindWorkspace', [
                'searchedEmail' => $email,
                'notFound' => true,
            ]);
        }

        if ($workspaces->count() === 1) {
            return redirect()->route('tenant.login', [
                'tenant_slug' => $workspaces->first()['slug'],
            ]);
        }

        return Inertia::render('Public/FindWorkspace', [
            'searchedEmail' => $email,
            'workspaces' => $workspaces,
        ]);
    }
}
