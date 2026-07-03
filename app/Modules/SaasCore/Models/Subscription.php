<?php

namespace App\Modules\SaasCore\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Subscription — a tenant's current relationship to a Plan.
 *
 * Tenant-owned: uses HasTenant so every query is constrained to the bound
 * tenant and tenant_id is auto-populated on create.
 */
class Subscription extends Model
{
    use HasTenant;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_PAUSED = 'paused';

    public const BILLING_MONTHLY = 'monthly';
    public const BILLING_ANNUAL = 'annual';

    public const GATEWAY_STRIPE = 'stripe';

    /** Stripe's own status once the tenant asked to cancel at period end. */
    public const STRIPE_STATUS_CANCELING = 'canceling';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'gateway',
        'gateway_subscription_id',
        'stripe_price_id',
        'stripe_status',
        'billing_cycle',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Offline payments recorded against this subscription (super admin). */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeTrialing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRIALING);
    }

    /**
     * Trialing AND the trial window has not yet lapsed.
     */
    public function scopeOnTrial(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_TRIALING)
            ->where('trial_ends_at', '>=', now());
    }

    /**
     * Whether this subscription currently grants access: active OR trialing.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_ACTIVE,
            self::STATUS_TRIALING,
        ], true);
    }
}
