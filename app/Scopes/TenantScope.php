<?php

namespace App\Scopes;

use App\Exceptions\TenantNotResolvedException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Eloquent scope enforcing row-level tenant isolation.
 *
 * Constrains every query on a tenant-owned model to the tenant bound at
 * app('current_tenant'). If no tenant is bound, it throws — there is no
 * silent "unscoped" fallback. Legitimate cross-context access (auth,
 * super admin) must opt out explicitly via withoutGlobalScope(self::class).
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('current_tenant')) {
            throw new TenantNotResolvedException();
        }

        $tenant = app('current_tenant');

        $builder->where($model->getTable().'.tenant_id', $tenant->id);
    }
}
