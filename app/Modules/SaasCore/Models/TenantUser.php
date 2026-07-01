<?php

namespace App\Modules\SaasCore\Models;

use App\Traits\HasTenant;
use App\Traits\ResetsPasswordWithinTenant;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * TenantUser — a staff/admin login for a single rental company (tenant).
 *
 * Deliberately SEPARATE from App\Models\User: the base User model is reserved
 * for future global/platform use and must not be entangled with tenant auth.
 *
 * Tenant isolation comes for free via HasTenant: every query (including the
 * auth provider's credential + id lookups) runs through TenantScope, so a
 * login can only ever resolve a user belonging to the bound tenant. Email is
 * unique per tenant, not globally — the same address may identify different
 * people across different tenants.
 */
class TenantUser extends Authenticatable
{
    use HasTenant;
    use HasRoles;
    use Notifiable;
    use ResetsPasswordWithinTenant;

    public const ROLE_ADMIN = 'tenant_admin';
    public const ROLE_STAFF = 'tenant_staff';
    public const ROLE_ACCOUNTS = 'tenant_accounts';

    /**
     * Spatie resolves roles/permissions against THIS guard. Without it, roles
     * would be looked up under the default 'web' guard and never match.
     */
    protected string $guard_name = 'tenant';

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'color_mode',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // The tenant() relationship is provided by the HasTenant trait.

    public function passwordResetGuardKey(): string
    {
        return 'tenant';
    }

    public function passwordResetRouteName(): string
    {
        return 'tenant.password.reset';
    }
}
