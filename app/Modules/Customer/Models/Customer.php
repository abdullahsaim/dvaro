<?php

namespace App\Modules\Customer\Models;

use App\Modules\Finance\Models\LedgerEntry;
use App\Scopes\TenantScope;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
    use SoftDeletes;

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
     * Whether the customer has an unpaid balance (cents > 0).
     *
     * Self-contained on purpose: it does NOT go through LedgerService, because
     * that path relies on the bound current_tenant (TenantScope) which is not
     * guaranteed outside a web request — queue jobs, scheduled commands, etc.
     * Instead we drop TenantScope and constrain explicitly by the customer's own
     * tenant_id, so the sum is correct (and tenant-safe) in ANY execution
     * context. Sign convention: positive amount = the customer owes money.
     */
    public function hasOutstandingBalance(): bool
    {
        return LedgerEntry::withoutGlobalScope(TenantScope::class)
            ->where('customer_id', $this->id)
            ->where('tenant_id', $this->tenant_id)
            ->sum('amount') > 0;
    }
}
