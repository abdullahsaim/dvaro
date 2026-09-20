<?php

namespace App\Modules\SaasCore\Actions;

use App\Actions\BaseAction;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Turns a pending invitation into a staff account. Runs on a PUBLIC route with
 * NO bound tenant, so the tenant is passed explicitly and tenant_id is set on
 * the new row by hand (HasTenant can't fill it here).
 *
 * The invitation is consumed in the same transaction as the account it
 * creates: a replayed link can never mint a second account.
 */
class AcceptStaffInvitationAction extends BaseAction
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {}

    public function execute(TenantUserInvitation $invitation, string $password): TenantUser
    {
        if (! $invitation->isPending()) {
            throw ValidationException::withMessages(['token' => __('common.staff.invite_invalid')]);
        }

        $user = DB::transaction(function () use ($invitation, $password): TenantUser {
            $user = TenantUser::query()->create([
                'tenant_id' => $invitation->tenant_id,
                'name' => $invitation->name,
                'email' => $invitation->email,
                'password' => $password, // hashed by the model cast
                'role' => $invitation->role,
                'is_active' => true,
            ]);

            $invitation->forceFill(['accepted_at' => now()])->save();

            return $user;
        });

        // The Spatie role must exist under the 'tenant' guard (TenantRolesSeeder).
        // A missing role must not undo an accepted invitation, so this is
        // outside the transaction and never fatal.
        try {
            $user->assignRole($invitation->role);
        } catch (Throwable) {
            // Left unassigned; the role column still governs access.
        }

        $this->audit->log(
            action: 'staff.joined',
            subjectType: AuditLog::SUBJECT_TENANT_USER,
            subjectId: (int) $user->id,
            subjectLabel: $user->email,
            new: ['role' => $user->role],
            tenant: $invitation->tenant,
        );

        return $user;
    }
}
