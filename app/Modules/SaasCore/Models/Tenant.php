<?php

namespace App\Modules\SaasCore\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * The tenant's access-granting subscription (active OR trialing).
     *
     * Subscription is tenant-scoped (HasTenant). The global TenantScope is
     * dropped here so this works in any context — onboarding, super admin,
     * console — where this Tenant may not be the bound request tenant.
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->withoutGlobalScope(TenantScope::class)
            ->whereIn('status', [
                Subscription::STATUS_ACTIVE,
                Subscription::STATUS_TRIALING,
            ])
            ->latestOfMany();
    }

    public function hasSubscription(): bool
    {
        return $this->activeSubscription()->exists();
    }

    /**
     * All subscriptions for this tenant, newest first (history view).
     *
     * TenantUser/Subscription are tenant-scoped; the global scope is dropped so
     * this resolves in any context (e.g. the super admin panel, where no tenant
     * is bound).
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)
            ->withoutGlobalScope(TenantScope::class)
            ->latest();
    }

    /**
     * Staff/admin logins belonging to this tenant. Scope dropped for the same
     * reason as subscriptions() — usable from the unbound super admin context.
     */
    public function users(): HasMany
    {
        return $this->hasMany(TenantUser::class)
            ->withoutGlobalScope(TenantScope::class);
    }

    /**
     * The plan behind the active subscription, or null when none.
     */
    public function activePlan(): ?Plan
    {
        return $this->activeSubscription?->plan;
    }

    /**
     * Whether the active plan enables the given module key.
     */
    public function hasModule(string $key): bool
    {
        return (bool) $this->activePlan()?->hasModule($key);
    }

    /**
     * Whether $current is within the active plan's limit for $key.
     *
     * No plan, or a negative limit (incl. an absent key => -1, meaning
     * unlimited), is always within limit.
     */
    public function withinLimit(string $key, int $current): bool
    {
        $plan = $this->activePlan();

        if ($plan === null) {
            return true;
        }

        $limit = $plan->getLimit($key);

        return $limit < 0 || $current < $limit;
    }
}
