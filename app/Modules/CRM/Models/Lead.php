<?php

namespace App\Modules\CRM\Models;

use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Models\TenantUser;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Lead — a prospective customer captured via the CRM intake workflow.
 *
 * Tenant-owned: HasTenant constrains every query to the bound tenant and
 * auto-fills tenant_id on create. NOTE: the PUBLIC intake form runs with NO
 * tenant bound, so the IntakeFormController resolves leads with TenantScope
 * dropped + an explicit tenant_id filter — never a bare Lead::query() there.
 *
 * A Lead is NOT a Customer. The Customer record is created only on conversion
 * (ConvertLeadAction), keeping CLAUDE.md's "customer exists after agreement"
 * pipeline intact — conversion here is the pre-agreement intake half.
 */
class Lead extends Model
{
    use HasTenant;
    use SoftDeletes;

    public const STATUS_NEW = 'new';
    public const STATUS_CONTACTED = 'contacted';
    public const STATUS_CONVERTED = 'converted';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_CONVERTED,
        self::STATUS_EXPIRED,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'address',
        'licence_number',
        'emergency_contact_name',
        'emergency_contact_phone',
        'rental_start_date',
        'rental_duration',
        'notes',
        'status',
        'token',
        'token_expires_at',
        'expires_manually',
        'submitted_at',
        'converted_at',
        'converted_customer_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'rental_start_date' => 'date',
            'token_expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'converted_at' => 'datetime',
            'expires_manually' => 'boolean',
        ];
    }

    public function convertedCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'converted_customer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(TenantUser::class, 'created_by');
    }

    /**
     * A link is expired when the admin manually killed it OR its expiry has
     * passed. NULL token_expires_at means "no time limit" — never expires on
     * its own.
     */
    public function isExpired(): bool
    {
        if ($this->expires_manually) {
            return true;
        }

        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    /**
     * Convertible = still in play (new/contacted), link alive, AND the customer
     * has actually submitted the form. We never convert a lead that hasn't been
     * filled in — there'd be no real data to seed the Customer with.
     */
    public function isConvertible(): bool
    {
        return in_array($this->status, [self::STATUS_NEW, self::STATUS_CONTACTED], true)
            && ! $this->isExpired()
            && $this->submitted_at !== null;
    }

    /** New or contacted, and the link is still alive. */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [self::STATUS_NEW, self::STATUS_CONTACTED])
            ->where('expires_manually', false)
            ->where(function (Builder $q) {
                $q->whereNull('token_expires_at')
                    ->orWhere('token_expires_at', '>', now());
            });
    }

    public function scopeConverted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_CONVERTED);
    }
}
