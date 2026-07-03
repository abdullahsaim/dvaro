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
    /**
     * Module keys a plan may enable (the modules_enabled[] set). Mirrors the
     * app/Modules folder structure. Used by the super admin plan form's module
     * checklist and to validate the submitted module list.
     */
    public const MODULE_KEYS = [
        'fleet',
        'rental',
        'agreement',
        'invoice',
        'finance',
        'workshop',
        'crm',
        'customer',
        'reporting',
        'ai',
        'notification',
        'cms',
    ];

    /**
     * Hard numeric limit keys a plan may define. Enforced by
     * PlanEnforcementService (an absent key => -1 => unlimited).
     */
    public const LIMIT_KEYS = [
        'max_vehicles',
        'max_staff_users',
        'max_customers',
        'max_storage_gb',
        'max_file_size_mb',
    ];

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
        'stripe_product_id',
        'stripe_monthly_price_id',
        'stripe_annual_price_id',
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
     * The Stripe Price id for a billing cycle, or null when the plan has not
     * been synced (stripe:sync-plans) for that cycle.
     */
    public function stripePriceIdFor(string $billingCycle): ?string
    {
        return $billingCycle === Subscription::BILLING_ANNUAL
            ? $this->stripe_annual_price_id
            : $this->stripe_monthly_price_id;
    }

    /**
     * The plan's price in cents for a billing cycle.
     */
    public function priceFor(string $billingCycle): int
    {
        return $billingCycle === Subscription::BILLING_ANNUAL
            ? (int) $this->price_annual
            : (int) $this->price_monthly;
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
