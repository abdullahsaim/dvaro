<?php

namespace App\Modules\Customer\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer — a tenant's rental customer.
 *
 * Tenant-owned: uses HasTenant so every query is constrained to the bound
 * tenant and tenant_id is auto-populated on create.
 *
 * Per CLAUDE.md a customer profile is created AFTER an agreement is signed —
 * this model is the data record only; that workflow lives in later sessions.
 */
class Customer extends Model
{
    use HasTenant;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'date_of_birth',
        'licence_number',
        'licence_expiry',
        'passport_number',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'risk_notes',
        'is_blacklisted',
        'blacklisted_reason',
    ];

    protected function casts(): array
    {
        return [
            // Encrypted at rest — sensitive identity documents (CLAUDE.md security).
            'licence_number' => 'encrypted',
            'passport_number' => 'encrypted',
            'date_of_birth' => 'date',
            'licence_expiry' => 'date',
            'is_blacklisted' => 'boolean',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function scopeBlacklisted(Builder $query): Builder
    {
        return $query->where('is_blacklisted', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_blacklisted', false);
    }

    /**
     * Whether the customer has an unpaid balance.
     *
     * Stub: the financial ledger does not exist yet (Invoice/Finance module,
     * later session). Returns false until the ledger is the system of record.
     */
    public function hasOutstandingBalance(): bool
    {
        return false;
    }
}
