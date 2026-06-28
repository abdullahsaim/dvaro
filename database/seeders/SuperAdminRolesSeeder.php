<?php

namespace Database\Seeders;

use App\Modules\SuperAdmin\Models\SuperAdmin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Seeds the four super admin roles (guard 'superadmin') and a default
 * platform_owner account from env vars.
 *
 * Idempotent (firstOrCreate). NOT wired into DatabaseSeeder — run on demand:
 *   php artisan db:seed --class=SuperAdminRolesSeeder
 *
 * Set SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD / SUPER_ADMIN_NAME before running.
 */
class SuperAdminRolesSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SuperAdmin::ROLES as $role) {
            Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'superadmin',
            ]);
        }

        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');
        $name = env('SUPER_ADMIN_NAME', 'Platform Owner');

        if (empty($email) || empty($password)) {
            $this->command?->warn(
                'SUPER_ADMIN_EMAIL / SUPER_ADMIN_PASSWORD not set — roles seeded, default account skipped.'
            );

            return;
        }

        $admin = SuperAdmin::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                // firstOrCreate hashes via the model's 'hashed' cast, but we set
                // it explicitly so a re-run never silently re-hashes a changed env.
                'password' => Hash::make($password),
                'role' => SuperAdmin::ROLE_PLATFORM_OWNER,
                'is_active' => true,
            ],
        );

        if (! $admin->hasRole(SuperAdmin::ROLE_PLATFORM_OWNER)) {
            $admin->assignRole(SuperAdmin::ROLE_PLATFORM_OWNER);
        }
    }
}
