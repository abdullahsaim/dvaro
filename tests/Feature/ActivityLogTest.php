<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Services\PlatformActivityLogger;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The platform-wide activity log screen: owner-only, lists every tenant's
 * platform-level events in one place, filters by action and by subject/actor
 * text.
 */
class ActivityLogTest extends TestCase
{
    use DatabaseTransactions;

    private function makeSuperAdmin(string $role = SuperAdmin::ROLE_PLATFORM_OWNER): SuperAdmin
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create([
            'name' => 'Owner', 'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret1234', 'role' => $role, 'is_active' => true,
        ]);
        $admin->assignRole($role);

        return $admin;
    }

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
    }

    public function test_only_platform_owner_can_view_the_activity_log(): void
    {
        $owner = $this->makeSuperAdmin(SuperAdmin::ROLE_PLATFORM_OWNER);
        $this->actingAs($owner, 'superadmin')->get('/superadmin/activity')->assertOk();

        $support = $this->makeSuperAdmin(SuperAdmin::ROLE_SUPPORT_AGENT);
        $this->actingAs($support, 'superadmin')->get('/superadmin/activity')->assertForbidden();
    }

    public function test_it_lists_events_across_every_tenant_newest_first(): void
    {
        $owner = $this->makeSuperAdmin();
        $tenantA = $this->makeTenant('act-a');
        $tenantB = $this->makeTenant('act-b');

        $logger = app(PlatformActivityLogger::class);
        $logger->log('tenant.suspended', PlatformActivityLog::SUBJECT_TENANT, $tenantA->id, $tenantA->name, tenant: $tenantA);
        $logger->log('tenant.activated', PlatformActivityLog::SUBJECT_TENANT, $tenantB->id, $tenantB->name, tenant: $tenantB);

        $this->actingAs($owner, 'superadmin')->get('/superadmin/activity')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('SuperAdmin/Activity/Index')
                ->where('logs.data.0.action', 'tenant.activated') // newest first
                ->where('logs.data.1.action', 'tenant.suspended')
                ->where('logs.data.0.tenant.name', $tenantB->name));
    }

    public function test_filtering_by_action_narrows_the_list(): void
    {
        $owner = $this->makeSuperAdmin();
        $tenant = $this->makeTenant('act-filter');

        $logger = app(PlatformActivityLogger::class);
        $logger->log('tenant.suspended', tenant: $tenant);
        $logger->log('tenant.activated', tenant: $tenant);

        $this->actingAs($owner, 'superadmin')
            ->get('/superadmin/activity?action=tenant.suspended')
            ->assertInertia(fn ($page) => $page
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'tenant.suspended'));
    }

    public function test_a_platform_level_event_has_no_tenant(): void
    {
        $owner = $this->makeSuperAdmin();

        app(PlatformActivityLogger::class)->log('plan.created', PlatformActivityLog::SUBJECT_PLAN, 1, 'Growth');

        $this->actingAs($owner, 'superadmin')->get('/superadmin/activity')
            ->assertInertia(fn ($page) => $page->where('logs.data.0.tenant', null));
    }

    public function test_the_log_is_append_only(): void
    {
        $tenant = $this->makeTenant('act-immutable');
        $log = app(PlatformActivityLogger::class)->log('tenant.suspended', tenant: $tenant);

        $this->expectException(\App\Exceptions\AppendOnlyException::class);
        $log->update(['action' => 'tampered']);
    }
}
