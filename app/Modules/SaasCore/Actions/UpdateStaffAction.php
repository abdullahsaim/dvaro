<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\PlanEnforcementService;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

/**
 * Changes a staff member's role or active status — the ONLY sanctioned path.
 *
 * GUARDRAILS (a company must never be able to lock itself out):
 *   - you can't change your own role or deactivate yourself;
 *   - the LAST ACTIVE ADMIN can't be demoted or deactivated;
 *   - reactivating counts against the plan's staff limit (a hard block).
 * Every change is audited with its old and new values.
 */
class UpdateStaffAction extends BaseAction
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PlanEnforcementService $planEnforcement,
    ) {}

    public function execute(TenantUser $staff, ?string $role, ?bool $isActive, TenantUser $actor): TenantUser
    {
        $role ??= $staff->role;
        $isActive ??= (bool) $staff->is_active;

        $isSelf = (int) $staff->id === (int) $actor->id;

        if ($isSelf && $role !== $staff->role) {
            throw ValidationException::withMessages(['role' => __('common.staff.cannot_change_own_role')]);
        }

        if ($isSelf && ! $isActive) {
            throw ValidationException::withMessages(['is_active' => __('common.staff.cannot_deactivate_self')]);
        }

        $losesAdmin = $staff->role === TenantUser::ROLE_ADMIN
            && ($role !== TenantUser::ROLE_ADMIN || ! $isActive);

        if ($losesAdmin && $this->activeAdminCount() <= 1) {
            throw ValidationException::withMessages(['role' => __('common.staff.last_admin')]);
        }

        // Turning someone back on takes a seat again.
        if ($isActive && ! $staff->is_active) {
            $this->planEnforcement->check('max_staff_users', TenantUser::query()->where('is_active', true)->count());
        }

        $old = ['role' => $staff->role, 'is_active' => (bool) $staff->is_active];

        $staff->forceFill(['role' => $role, 'is_active' => $isActive])->save();

        // Keep the Spatie role in step with the column (the guard's role checks
        // read the assigned role, not just the string).
        if ($old['role'] !== $role) {
            $staff->syncRoles([$role]);
        }

        $this->audit->log(
            action: 'staff.updated',
            subjectType: AuditLog::SUBJECT_TENANT_USER,
            subjectId: (int) $staff->id,
            subjectLabel: $staff->email,
            old: $old,
            new: ['role' => $role, 'is_active' => $isActive],
        );

        return $staff;
    }

    private function activeAdminCount(): int
    {
        return TenantUser::query()
            ->where('role', TenantUser::ROLE_ADMIN)
            ->where('is_active', true)
            ->count();
    }
}
