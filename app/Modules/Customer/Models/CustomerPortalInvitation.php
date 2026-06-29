<?php

namespace App\Modules\Customer\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CustomerPortalInvitation — a tenant-issued invitation that lets a customer
 * set a password and activate their Customer Portal login (CustomerUser).
 *
 * Tenant-owned via HasTenant. The token is an unguessable UUID; the public
 * accept route resolves the invitation scope-free by token AND explicit
 * tenant_id, so an invitation from one tenant can never be redeemed on another
 * tenant's portal URL. Invitations expire 7 days after creation.
 */
class CustomerPortalInvitation extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'email',
        'token',
        'accepted_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Past its 7-day window. */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /** Already redeemed — a CustomerUser was created from it. */
    public function isAccepted(): bool
    {
        return $this->accepted_at !== null;
    }
}
