<?php

namespace App\Modules\SaasCore\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Plan — a platform-wide subscription package.
 *
 * Plans are NOT tenant-owned: they are defined once by the super admin and
 * shared across every tenant. This model must never use the HasTenant trait
 * or the TenantScope global scope.
 *
 * Monetary values (price_monthly, price_annual) are stored as integer cents.
 */
class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_annual',
        'is_active',
        'is_free',
        'trial_days',
        'modules',
        'limits',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'limits' => 'array',
            'is_active' => 'boolean',
            'is_free' => 'boolean',
            'price_monthly' => 'integer',
            'price_annual' => 'integer',
            'trial_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Whether this plan enables the given module key.
     */
    public function hasModule(string $key): bool
    {
        return in_array($key, $this->modules ?? [], true);
    }

    /**
     * Resolve a hard limit by key.
     *
     * Returns -1 when the key is absent, meaning UNLIMITED. This keeps an
     * incomplete plan config from hard-blocking every action — enforcement
     * treats any value < 0 as "no limit".
     */
    public function getLimit(string $key): int
    {
        $limits = $this->limits ?? [];

        if (! array_key_exists($key, $limits)) {
            return -1;
        }

        return (int) $limits[$key];
    }
}
