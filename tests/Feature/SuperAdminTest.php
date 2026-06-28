<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Events\TenantActivated;
use App\Modules\SuperAdmin\Events\TenantSuspended;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\SuperAdmin\Services\PlatformSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The super admin panel is a wholly separate guard + route group. These tests
 * exercise the real HTTP stack: guard isolation (neither guard can use the
 * other's routes), tenant suspend/activate (+ events), and the session-only
 * impersonation flow (login into the tenant guard + marker, stop clears both).
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class SuperAdminTest extends TestCase
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

    private function makeSuperAdmin(string $role = SuperAdmin::ROLE_PLATFORM_OWNER, array $attrs = []): SuperAdmin
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create(array_merge([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret1234',
            'role' => $role,
            'is_active' => true,
        ], $attrs));

        $admin->assignRole($role);

        return $admin;
    }

    private function makeTenantAdmin(Tenant $tenant, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Tenant Admin',
            'email' => $email,
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    public function test_super_admin_can_log_in_and_reach_dashboard(): void
    {
        $admin = $this->makeSuperAdmin();

        $response = $this->post('/superadmin/login', [
            'email' => $admin->email,
            'password' => 'secret1234',
        ]);

        $response->assertRedirect(route('superadmin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'superadmin');
        $this->assertNotNull($admin->fresh()->last_login_at);
    }

    public function test_inactive_super_admin_cannot_log_in(): void
    {
        $admin = $this->makeSuperAdmin(attrs: ['is_active' => false]);

        $this->post('/superadmin/login', [
            'email' => $admin->email,
            'password' => 'secret1234',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('superadmin');
    }

    public function test_super_admin_cannot_access_tenant_routes(): void
    {
        $tenant = $this->makeTenant('alpha-co');
        $admin = $this->makeSuperAdmin();

        // Authenticated on the superadmin guard ONLY — the tenant guard is empty,
        // so the tenant dashboard bounces to the tenant login.
        $response = $this->actingAs($admin, 'superadmin')
            ->get("/app/{$tenant->slug}/dashboard");

        $response->assertRedirect(route('tenant.login', ['tenant_slug' => $tenant->slug]));
    }

    public function test_tenant_user_cannot_access_super_admin_routes(): void
    {
        $tenant = $this->makeTenant('beta-co');
        $user = $this->makeTenantAdmin($tenant, 'admin@beta.test');

        $response = $this->actingAs($user, 'tenant')->get('/superadmin/dashboard');

        $response->assertRedirect(route('superadmin.login'));
    }

    public function test_super_admin_can_suspend_and_activate_a_tenant(): void
    {
        Event::fake([TenantSuspended::class, TenantActivated::class]);

        $tenant = $this->makeTenant('gamma-co');
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'superadmin')
            ->post(route('superadmin.tenants.suspend', $tenant))
            ->assertRedirect();

        $this->assertSame(Tenant::STATUS_SUSPENDED, $tenant->fresh()->status);
        Event::assertDispatched(TenantSuspended::class);

        // A suspended tenant's app is hard-blocked by TenantMiddleware (403).
        $this->get("/app/{$tenant->slug}/login")->assertStatus(403);

        $this->actingAs($admin, 'superadmin')
            ->post(route('superadmin.tenants.activate', $tenant))
            ->assertRedirect();

        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->fresh()->status);
        Event::assertDispatched(TenantActivated::class);
    }

    public function test_impersonation_logs_into_tenant_guard_and_stops_cleanly(): void
    {
        $tenant = $this->makeTenant('delta-co');
        $user = $this->makeTenantAdmin($tenant, 'admin@delta.test');
        $admin = $this->makeSuperAdmin();

        // Impersonate → redirected to the tenant dashboard, tenant guard logged
        // in as the tenant admin, session marker stamped.
        $response = $this->actingAs($admin, 'superadmin')
            ->post(route('superadmin.tenants.impersonate', $tenant));

        $response->assertRedirect(route('tenant.dashboard', ['tenant_slug' => $tenant->slug]));
        $response->assertSessionHas('impersonator_superadmin_id', $admin->id);
        $this->assertSame($user->id, Auth::guard('tenant')->id());

        // Stop → BOTH cleared: tenant guard logged out AND marker removed.
        $stop = $this->actingAs($admin, 'superadmin')
            ->post(route('superadmin.stop-impersonating'));

        $stop->assertRedirect(route('superadmin.dashboard'));
        $stop->assertSessionMissing('impersonator_superadmin_id');
        $this->assertNull(Auth::guard('tenant')->id());
    }

    public function test_platform_settings_update_persists_and_recaches(): void
    {
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'superadmin')->put(route('superadmin.settings.update'), [
            'platform_name' => 'Fleetora',
            'support_email' => 'help@dvaro.test',
            'free_trial_days' => 21,
            'manual_tenant_approval' => true,
            'free_trial_enabled' => true,
            'freemium_enabled' => false,
            'maintenance_mode' => false,
        ])->assertRedirect();

        $svc = app(PlatformSettingsService::class);
        $this->assertSame('Fleetora', $svc->get('platform_name'));
        $this->assertSame(21, $svc->get('free_trial_days'));
        $this->assertTrue($svc->get('manual_tenant_approval'));
    }
}
