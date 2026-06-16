<?php

namespace App\Modules\SaasCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tenant — the root of the multi-tenancy model.
 *
 * This model IS the tenant, so it is deliberately NOT tenant-scoped:
 * it must never use the HasTenant trait or the TenantScope global scope.
 */
class Tenant extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_TRIAL = 'trial';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'name',
        'slug',
        'status',
        'plan_id',
        'settings',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Auto-generate a slug from the name when one is not supplied.
        static::creating(function (Tenant $tenant): void {
            if (empty($tenant->slug) && ! empty($tenant->name)) {
                $tenant->slug = Str::slug($tenant->name);
            }
        });
    }

    /**
     * Resolve route-model bindings by slug (path-based tenancy).
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
