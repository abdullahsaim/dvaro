<?php

namespace App\Modules\Customer\Models;

use App\Traits\HasTenant;
use App\Traits\ResetsPasswordWithinTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * CustomerUser — the login account for an end customer (car renter) in the
 * Customer Portal (the 'customer' guard, the FIFTH and final auth portal).
 *
 * Deliberately SEPARATE from the Customer model: Customer is the profile/ledger
 * record (created after an agreement is signed); CustomerUser is purely the auth
 * account that lets that customer log in. One CustomerUser maps to one Customer.
 *
 * Tenant isolation comes for free via HasTenant: every query (including the auth
 * provider's credential + id lookups) runs through TenantScope, so a login can
 * only ever resolve a customer-user of the bound tenant. Email is unique per
 * tenant, not globally — the same address may identify different customers
 * across different tenants. The customer guard never shares state with the
 * tenant, superadmin or mechanic guards.
 */
class CustomerUser extends Authenticatable
{
    use HasTenant;
    use Notifiable;
    use ResetsPasswordWithinTenant;

    /**
     * The guard this account authenticates under. There is a single customer
     * role, so no Spatie roles are needed here — this simply documents and pins
     * the guard the portal uses.
     */
    protected string $guard_name = 'customer';

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function passwordResetGuardKey(): string
    {
        return 'customer';
    }

    public function passwordResetRouteName(): string
    {
        return 'customer.password.reset';
    }
}
