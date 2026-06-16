<?php

namespace App\Traits;

use App\Modules\SaasCore\Models\Tenant;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Apply to any model that belongs to a tenant.
 *
 *   - Registers the TenantScope global scope (every query is constrained to
 *     the current tenant; querying with no tenant bound throws).
 *   - Auto-populates tenant_id from the bound tenant on create.
 *
 * The Tenant model itself must NEVER use this trait.
 */
trait HasTenant
{
    public static function bootHasTenant(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function ($model): void {
            if (empty($model->tenant_id) && app()->bound('current_tenant')) {
                $model->tenant_id = app('current_tenant')->id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
