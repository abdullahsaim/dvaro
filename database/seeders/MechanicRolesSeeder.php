<?php

namespace Database\Seeders;

use App\Modules\Workshop\Models\Mechanic;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the two mechanic-portal roles under the 'mechanic' guard.
 *
 * Like the tenant roles, these are global platform rows (not tenant-scoped);
 * per-tenant authorisation comes from assigning them to tenant-scoped Mechanic
 * records. The senior role unlocks editing any tenant log (MechanicPolicy);
 * the base role is own-logs-only. Idempotent — safe to run repeatedly.
 *
 * NOT wired into DatabaseSeeder; run explicitly when needed:
 *   php artisan db:seed --class=MechanicRolesSeeder
 */
class MechanicRolesSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            Mechanic::ROLE_MECHANIC,
            Mechanic::ROLE_SENIOR,
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'mechanic',
            ]);
        }
    }
}
