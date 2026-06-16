<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global Eloquent scope enforcing row-level tenant isolation.
 *
 * SKELETON ONLY — no query logic yet.
 *
 * Every tenant-owned model must register this scope:
 *
 *     protected static function booted(): void
 *     {
 *         static::addGlobalScope(new TenantScope());
 *     }
 *
 * The concrete implementation will constrain every query by the current
 * tenant_id resolved from the request/context. No tenant query may bypass it.
 */
class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // TODO: constrain by current tenant_id once tenant context is wired.
    }
}
