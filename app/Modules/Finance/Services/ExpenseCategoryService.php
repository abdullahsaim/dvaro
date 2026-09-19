<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use App\Services\BaseService;
use Illuminate\Validation\ValidationException;

/**
 * Tenant expense categories: the three client-named defaults (daily /
 * government / utilities — created at onboarding, backfilled by migration, and
 * re-ensured lazily) plus tenant-created ones. Categories are renamed or
 * hidden, NEVER deleted (historic expenses keep their category).
 */
class ExpenseCategoryService extends BaseService
{
    /**
     * Idempotently create any missing default for $tenant. Works with or
     * without a bound tenant (onboarding runs before one is bound).
     */
    public function ensureDefaults(Tenant $tenant): void
    {
        $existing = ExpenseCategory::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->whereNotNull('system_key')
            ->pluck('system_key')
            ->all();

        foreach (ExpenseCategory::DEFAULTS as $key => [$name, $order]) {
            if (in_array($key, $existing, true)) {
                continue;
            }

            // A tenant may already have a custom category with the default's
            // name — suffix to keep (tenant_id, name) unique.
            $taken = ExpenseCategory::withoutGlobalScope(TenantScope::class)
                ->where('tenant_id', $tenant->id)
                ->where('name', $name)
                ->exists();

            ExpenseCategory::withoutGlobalScope(TenantScope::class)->create([
                'tenant_id' => $tenant->id,
                'name' => $taken ? "{$name} (default)" : $name,
                'system_key' => $key,
                'sort_order' => $order,
            ]);
        }
    }

    public function create(string $name): ExpenseCategory
    {
        $this->assertNameFree($name);

        return ExpenseCategory::create([
            'name' => trim($name),
            'sort_order' => 100,
        ]);
    }

    public function update(ExpenseCategory $category, string $name, bool $hidden): ExpenseCategory
    {
        $this->assertNameFree($name, $category->id);

        $category->update(['name' => trim($name), 'is_hidden' => $hidden]);

        return $category;
    }

    /** Unique per tenant, case-insensitive. */
    private function assertNameFree(string $name, ?int $ignoreId = null): void
    {
        $clash = ExpenseCategory::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($name))])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($clash) {
            throw ValidationException::withMessages(['name' => __('common.expenses.category_exists')]);
        }
    }
}
