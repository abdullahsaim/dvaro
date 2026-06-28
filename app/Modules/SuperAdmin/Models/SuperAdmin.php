<?php

namespace App\Modules\SuperAdmin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * SuperAdmin — a platform-owner login for the Fleetora super admin panel.
 *
 * Platform-wide by design: this model deliberately does NOT use HasTenant and
 * is NEVER subject to TenantScope. A super admin spans every tenant; there is
 * no tenant_id column. Email is globally unique (there is one platform).
 *
 * The superadmin guard is wholly separate from the tenant / customer / mechanic
 * guards and must never share session state with them.
 */
class SuperAdmin extends Authenticatable
{
    use HasRoles;
    use Notifiable;
    use SoftDeletes;

    public const ROLE_PLATFORM_OWNER = 'platform_owner';
    public const ROLE_BILLING_MANAGER = 'billing_manager';
    public const ROLE_SUPPORT_AGENT = 'support_agent';
    public const ROLE_CONTENT_MANAGER = 'content_manager';

    public const ROLES = [
        self::ROLE_PLATFORM_OWNER,
        self::ROLE_BILLING_MANAGER,
        self::ROLE_SUPPORT_AGENT,
        self::ROLE_CONTENT_MANAGER,
    ];

    /**
     * Spatie resolves roles/permissions against THIS guard. Without it, roles
     * would be looked up under the default 'web' guard and never match.
     */
    protected string $guard_name = 'superadmin';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
