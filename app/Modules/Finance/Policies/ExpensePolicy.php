<?php

namespace App\Modules\Finance\Policies;

use App\Modules\Finance\Models\Expense;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Expenses (client decision, Session 32):
 *   tenant_admin / tenant_accounts — everything (record, edit any, void,
 *                                     manage categories)
 *   tenant_staff                    — record + view; edit ONLY their own
 *                                     expense on the SAME DAY it was entered;
 *                                     never void or manage categories
 *   mechanics                       — no access (different guard entirely)
 *
 * Evaluated against the TENANT guard user — controllers call
 * Gate::forUser(auth('tenant')->user()). Cross-tenant ids already 404 via
 * TenantScope binding; sameTenant() is defence-in-depth.
 */
class ExpensePolicy
{
    public function viewAny(TenantUser $user): bool
    {
        return true;
    }

    public function view(TenantUser $user, Expense $expense): bool
    {
        return $this->sameTenant($user, $expense);
    }

    public function create(TenantUser $user): bool
    {
        return true;
    }

    public function update(TenantUser $user, Expense $expense): bool
    {
        if (! $this->sameTenant($user, $expense) || $expense->isVoided()) {
            return false;
        }

        if ($this->isManager($user)) {
            return true;
        }

        return (int) $expense->created_by === (int) $user->id && $expense->created_at?->isToday();
    }

    public function void(TenantUser $user, Expense $expense): bool
    {
        return $this->sameTenant($user, $expense) && $this->isManager($user) && ! $expense->isVoided();
    }

    public function manageCategories(TenantUser $user): bool
    {
        return $this->isManager($user);
    }

    public static function isManager(TenantUser $user): bool
    {
        return in_array($user->role, [TenantUser::ROLE_ADMIN, TenantUser::ROLE_ACCOUNTS], true);
    }

    private function sameTenant(TenantUser $user, Expense $expense): bool
    {
        return (int) $user->tenant_id === (int) $expense->tenant_id;
    }
}
