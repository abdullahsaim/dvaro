<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Per-user color-mode preference endpoint. One shared controller is mounted in
 * every authenticated guard group; it detects the active guard and persists the
 * choice on that guard's user model. These cover a tenant-scoped guard (slug in
 * the path) and the platform-wide super admin guard (no slug).
 *
 * DatabaseTransactions (rolls back) — never migrate:fresh on the shared DB.
 */
class UserPreferenceTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    public function test_tenant_user_can_persist_color_mode(): void
    {
        $tenant = $this->makeTenant('pref-tenant');
        app()->instance('current_tenant', $tenant);
        $user = TenantUser::create([
            'name' => 'Pref User',
            'email' => 'pref@example.com',
            'password' => 'password123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);

        $response = $this
            ->actingAs($user, 'tenant')
            ->put('/app/pref-tenant/preferences/color-mode', ['color_mode' => 'dark']);

        $response->assertNoContent();
        $this->assertSame('dark', $user->refresh()->color_mode);
    }

    public function test_invalid_color_mode_is_rejected(): void
    {
        $tenant = $this->makeTenant('pref-tenant-2');
        app()->instance('current_tenant', $tenant);
        $user = TenantUser::create([
            'name' => 'Pref User',
            'email' => 'pref2@example.com',
            'password' => 'password123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);

        $response = $this
            ->actingAs($user, 'tenant')
            ->put('/app/pref-tenant-2/preferences/color-mode', ['color_mode' => 'blue']);

        $response->assertSessionHasErrors('color_mode');
        $this->assertSame('system', $user->refresh()->color_mode); // unchanged default
    }

    public function test_super_admin_can_persist_color_mode_on_no_slug_route(): void
    {
        $admin = SuperAdmin::create([
            'name' => 'Owner',
            'email' => 'owner-pref@example.com',
            'password' => 'password123',
            'role' => SuperAdmin::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);

        $response = $this
            ->actingAs($admin, 'superadmin')
            ->put('/superadmin/preferences/color-mode', ['color_mode' => 'light']);

        $response->assertNoContent();
        $this->assertSame('light', $admin->refresh()->color_mode);
    }

    public function test_guest_cannot_reach_the_endpoint(): void
    {
        $this->makeTenant('pref-guest');

        $response = $this->put('/app/pref-guest/preferences/color-mode', ['color_mode' => 'dark']);

        // Behind auth:tenant → unauthenticated is redirected, never 204.
        $response->assertRedirect();
    }
}
