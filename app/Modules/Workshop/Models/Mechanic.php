<?php

namespace App\Modules\Workshop\Models;

use App\Traits\HasTenant;
use App\Traits\ResetsPasswordWithinTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Mechanic — a workshop login for a single rental company (tenant).
 *
 * The FOURTH portal guard ('mechanic'), completely separate from the tenant,
 * super admin and customer guards — never mix them. Like TenantUser, this is a
 * dedicated Authenticatable, NOT App\Models\User.
 *
 * Tenant isolation comes for free via HasTenant: every query (including the
 * auth provider's credential + id lookups) runs through TenantScope, so a login
 * can only ever resolve a mechanic belonging to the bound tenant. Email is
 * unique per tenant, not globally.
 */
class Mechanic extends Authenticatable
{
    use HasRoles;
    use HasTenant;
    use Notifiable;
    use ResetsPasswordWithinTenant;
    use SoftDeletes;

    public const ROLE_MECHANIC = 'mechanic';
    public const ROLE_SENIOR = 'senior_mechanic';

    /**
     * Spatie resolves roles/permissions against THIS guard. Without it, roles
     * would be looked up under the default 'web' guard and never match.
     */
    protected string $guard_name = 'mechanic';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'pin',
        'password',
        'is_active',
        'last_login_at',
        'color_mode',
    ];

    protected $hidden = [
        'pin',
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'pin' => 'hashed',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    /**
     * Service logs this mechanic has worked on.
     */
    public function serviceLogs()
    {
        return $this->hasMany(ServiceLog::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function passwordResetGuardKey(): string
    {
        return 'mechanic';
    }

    public function passwordResetRouteName(): string
    {
        return 'mechanic.password.reset';
    }
}
