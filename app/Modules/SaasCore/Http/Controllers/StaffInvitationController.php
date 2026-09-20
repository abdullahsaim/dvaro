<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SaasCore\Actions\AcceptStaffInvitationAction;
use App\Modules\SaasCore\Http\Requests\AcceptStaffInvitationRequest;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Scopes\TenantScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PUBLIC "accept your invitation" flow — the invitee has no account yet, so
 * there is no auth here. The unguessable token is the only credential.
 *
 * TENANT CONTEXT: these routes sit in the tenant group, so TenantMiddleware
 * HAS bound the tenant from {tenant_slug}. The invitation is still fetched
 * scope-free with an explicit tenant_id so the lookup can never drift to
 * another tenant's token.
 *
 * On success the new staff member is signed in immediately — they just proved
 * control of the mailbox and chose the password.
 */
class StaffInvitationController extends Controller
{
    public function show(string $token): Response
    {
        $invitation = $this->find($token);

        return Inertia::render('Auth/AcceptStaffInvitation', [
            'valid' => $invitation !== null && $invitation->isPending(),
            'status' => $invitation?->status(),
            'name' => $invitation?->name,
            'email' => $invitation?->email,
            'companyName' => app('current_tenant')->name,
            'token' => $token,
        ]);
    }

    public function accept(
        AcceptStaffInvitationRequest $request,
        string $token,
        AcceptStaffInvitationAction $action,
    ): RedirectResponse {
        $invitation = $this->find($token);

        abort_if($invitation === null, 404);

        $user = $action->execute($invitation, $request->string('password')->toString());

        Auth::guard('tenant')->login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('tenant.dashboard', ['tenant_slug' => app('current_tenant')->slug])
            ->with('success', __('common.staff.welcome'));
    }

    private function find(string $token): ?TenantUserInvitation
    {
        return TenantUserInvitation::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', app('current_tenant')->id)
            ->where('token', $token)
            ->first();
    }
}
