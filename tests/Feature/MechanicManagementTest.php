<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tenant-admin management of workshop logins (Mechanic CRUD). Exercises the real
 * HTTP stack (TenantMiddleware + auth:tenant + ManageMechanicPolicy) and proves
 * a created mechanic can actually log into the mechanic portal (credential
 * hashing through the model cast).
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class MechanicManagementTest extends TestCase
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

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function makeUser(Tenant $tenant, string $role): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'User '.$role,
            'email' => $role.'@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'role' => $role,
        ]);
    }

    public function test_tenant_admin_can_create_a_mechanic_who_can_then_log_in(): void
    {
        $t = $this->makeTenant('shop-a');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        $this->actingAs($admin, 'tenant')
            ->post('/app/shop-a/mechanics', [
                'name' => 'Sam Smith',
                'email' => 'sam@shop.test',
                'phone' => '0400000000',
                'password' => 'mechanic-pass',
                'is_active' => true,
            ])
            ->assertRedirect('/app/shop-a/mechanics');

        app()->instance('current_tenant', $t);
        $mechanic = Mechanic::where('email', 'sam@shop.test')->first();
        $this->assertNotNull($mechanic);
        $this->assertTrue($mechanic->is_active);

        // The password must have been hashed (model cast) and work at login.
        $this->forgetGuards();
        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'mechanic-pass',
        ])->assertRedirect('/mechanic/shop-a/dashboard');
        $this->assertAuthenticated('mechanic');
    }

    public function test_non_admin_tenant_user_cannot_create_a_mechanic(): void
    {
        $t = $this->makeTenant('shop-a');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')
            ->post('/app/shop-a/mechanics', [
                'name' => 'Sam',
                'email' => 'sam@shop.test',
                'password' => 'mechanic-pass',
                'is_active' => true,
            ])
            ->assertForbidden();

        app()->instance('current_tenant', $t);
        $this->assertSame(0, Mechanic::where('email', 'sam@shop.test')->count());
    }

    public function test_editing_with_blank_credentials_keeps_the_existing_password(): void
    {
        $t = $this->makeTenant('shop-a');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        app()->instance('current_tenant', $t);
        $mechanic = Mechanic::create([
            'name' => 'Sam',
            'email' => 'sam@shop.test',
            'password' => 'original-pass',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'tenant')
            ->put('/app/shop-a/mechanics/'.$mechanic->id, [
                'name' => 'Sam Renamed',
                'email' => 'sam@shop.test',
                'pin' => '',
                'password' => '',
                'is_active' => true,
            ])
            ->assertRedirect('/app/shop-a/mechanics');

        // Name changed; the original password still authenticates (untouched).
        $this->assertSame('Sam Renamed', $mechanic->fresh()->name);

        $this->forgetGuards();
        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'original-pass',
        ])->assertRedirect('/mechanic/shop-a/dashboard');
        $this->assertAuthenticated('mechanic');
    }

    public function test_tenant_admin_can_soft_delete_a_mechanic(): void
    {
        $t = $this->makeTenant('shop-a');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        app()->instance('current_tenant', $t);
        $mechanic = Mechanic::create([
            'name' => 'Sam',
            'email' => 'sam@shop.test',
            'password' => 'original-pass',
            'is_active' => true,
        ]);

        $this->actingAs($admin, 'tenant')
            ->delete('/app/shop-a/mechanics/'.$mechanic->id)
            ->assertRedirect('/app/shop-a/mechanics');

        app()->instance('current_tenant', $t);
        $this->assertSoftDeleted('mechanics', ['id' => $mechanic->id]);
    }
}
