<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\SendStaffInvitationJob;
use App\Modules\SaasCore\Actions\InviteStaffAction;
use App\Modules\SaasCore\Actions\UpdateStaffAction;
use App\Modules\SaasCore\Http\Requests\InviteStaffRequest;
use App\Modules\SaasCore\Http\Requests\UpdateStaffRequest;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Modules\SaasCore\Services\UsageService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Staff. Until now a rental company could not add a staff member at
 * all — accounts existed only via seeder/tinker. Invitations let people set
 * their own password; DVARO never creates or emails one.
 *
 * ADMIN-ONLY for every write (roles are an access-control decision); other
 * staff may view the list. Guardrails against locking the company out live in
 * UpdateStaffAction. {staff}/{invitation} bind through TenantScope, so a
 * cross-tenant id 404s.
 */
class StaffController extends Controller
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function index(UsageService $usage): Response
    {
        $tenant = app('current_tenant');

        return Inertia::render('Settings/Staff', [
            'staff' => TenantUser::query()
                ->orderByDesc('is_active')
                ->orderBy('name')
                ->get(['id', 'name', 'email', 'role', 'is_active', 'last_login_at', 'created_at']),
            'invitations' => TenantUserInvitation::query()
                ->latest('id')
                ->limit(25)
                ->get(['id', 'name', 'email', 'role', 'expires_at', 'accepted_at', 'revoked_at'])
                ->map(fn (TenantUserInvitation $i) => [
                    ...$i->only(['id', 'name', 'email', 'role', 'expires_at']),
                    'status' => $i->status(),
                ]),
            'roles' => TenantUser::ROLES,
            'currentUserId' => auth('tenant')->id(),
            'canManage' => $this->isAdmin(),
            'usage' => $usage->getUsage($tenant)['staff'],
        ]);
    }

    public function invite(InviteStaffRequest $request, InviteStaffAction $action): RedirectResponse
    {
        $this->authorizeAdmin();

        $action->execute(
            $request->string('name')->toString(),
            $request->string('email')->toString(),
            $request->string('role')->toString(),
            auth('tenant')->id(),
        );

        return back()->with('success', __('common.staff.invited'));
    }

    public function update(UpdateStaffRequest $request, TenantUser $staff, UpdateStaffAction $action): RedirectResponse
    {
        $this->authorizeAdmin();

        $action->execute(
            $staff,
            $request->filled('role') ? $request->string('role')->toString() : null,
            $request->has('is_active') ? $request->boolean('is_active') : null,
            auth('tenant')->user(),
        );

        return back()->with('success', __('common.staff.updated'));
    }

    public function resendInvitation(TenantUserInvitation $invitation): RedirectResponse
    {
        $this->authorizeAdmin();

        abort_unless($invitation->isPending(), 404);

        SendStaffInvitationJob::dispatch((int) $invitation->tenant_id, (int) $invitation->id);

        return back()->with('success', __('common.staff.invite_resent'));
    }

    public function revokeInvitation(TenantUserInvitation $invitation): RedirectResponse
    {
        $this->authorizeAdmin();

        abort_unless($invitation->isPending(), 404);

        $invitation->forceFill(['revoked_at' => now()])->save();

        $this->audit->log(
            action: 'staff.invite_revoked',
            subjectType: AuditLog::SUBJECT_INVITATION,
            subjectId: (int) $invitation->id,
            subjectLabel: $invitation->email,
        );

        return back()->with('success', __('common.staff.invite_revoked'));
    }

    private function isAdmin(): bool
    {
        $user = auth('tenant')->user();

        return $user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN;
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }
}
