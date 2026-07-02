<?php

namespace App\Modules\SaasCore\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UpgradeRequest — a tenant's "contact us to upgrade" request.
 *
 * Until Stripe/PayPal self-service billing lands (Session C), a tenant admin
 * cannot self-assign a plan. Instead they submit an UpgradeRequest; a super
 * admin reviews it, contacts the tenant, and manually assigns the plan.
 *
 * Tenant-owned: uses HasTenant so tenant_id is auto-populated and every tenant
 * query is scoped. The super admin queue reads across tenants and must drop
 * TenantScope explicitly (there is no bound tenant in that context).
 */
class UpgradeRequest extends Model
{
    use HasTenant;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Every valid upgrade-request status. */
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_CONTACTED,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'requested_plan_id',
        'current_plan_id',
        'status',
        'notes',
    ];

    // The tenant() relationship is provided by the HasTenant trait.

    public function requestedPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'requested_plan_id');
    }

    public function currentPlan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'current_plan_id');
    }
}
