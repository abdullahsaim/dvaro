<?php

namespace App\Modules\SaasCore\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A pending staff invitation. The invitee sets their own password from the
 * emailed link — DVARO never creates or emails a password.
 *
 * Tenant-owned via HasTenant. The token is an unguessable UUID; the PUBLIC
 * accept route has no bound tenant, so it must resolve invitations scope-free
 * by token AND an explicit tenant_id (see AcceptStaffInvitationController).
 */
class TenantUserInvitation extends Model
{
    use HasTenant;

    public const EXPIRY_DAYS = 7;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'role',
        'token',
        'invited_by',
        'expires_at',
        'accepted_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }

    public function status(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('accepted_at')->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
