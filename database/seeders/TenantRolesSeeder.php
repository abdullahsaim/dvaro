<?php

namespace Database\Seeders;

use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the three tenant-portal roles under the 'tenant' guard.
 *
 * These roles are global to the platform (they are not tenant-scoped rows);
 * per-tenant authorisation comes from assigning them to tenant-scoped
 * TenantUser records. Idempotent — safe to run repeatedly.
 *
 * NOT wired into DatabaseSeeder yet; run explicitly when test/seed data is
 * actually needed:  php artisan db:seed --class=TenantRolesSeeder
 */
class TenantRolesSeeder extends Seeder
{
    public function run(): void
    {
        // Clear cached roles/permissions so freshly created roles resolve.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            TenantUser::ROLE_ADMIN,
            TenantUser::ROLE_STAFF,
            TenantUser::ROLE_ACCOUNTS,
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'tenant',
            ]);
        }
    }
}
