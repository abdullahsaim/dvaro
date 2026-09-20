<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Jobs\SendStaffInvitationJob;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Invites a staff member. DVARO never creates or emails a password — the
 * invitee sets their own from the emailed link (SendStaffInvitationJob).
 *
 * Plan limit max_staff_users is a HARD BLOCK counted as existing staff PLUS
 * pending invitations, so a company can't overshoot its plan by sending ten
 * invites at once.
 */
class InviteStaffAction extends BaseAction
{
    public function __construct(
        private readonly PlanEnforcementService $planEnforcement,
        private readonly AuditLogger $audit,
    ) {}

    public function execute(string $name, string $email, string $role, ?int $invitedBy = null): TenantUserInvitation
    {
        $email = mb_strtolower(trim($email));

        if (TenantUser::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => __('common.staff.email_taken')]);
        }

        if (TenantUserInvitation::query()->pending()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => __('common.staff.already_invited')]);
        }

        // Pending invitations count toward the plan limit — they are seats
        // about to be filled.
        $this->planEnforcement->check(
            'max_staff_users',
            TenantUser::query()->count() + TenantUserInvitation::query()->pending()->count(),
        );

        $invitation = DB::transaction(fn () => TenantUserInvitation::query()->create([
            'name' => trim($name),
            'email' => $email,
            'role' => $role,
            'token' => (string) Str::uuid(),
            'invited_by' => $invitedBy,
            'expires_at' => now()->addDays(TenantUserInvitation::EXPIRY_DAYS),
        ]));

        SendStaffInvitationJob::dispatch($invitation->tenant_id, $invitation->id);

        $this->audit->log(
            action: 'staff.invited',
            subjectType: AuditLog::SUBJECT_INVITATION,
            subjectId: $invitation->id,
            subjectLabel: $email,
            new: ['role' => $role, 'email' => $email],
        );

        return $invitation;
    }
}
