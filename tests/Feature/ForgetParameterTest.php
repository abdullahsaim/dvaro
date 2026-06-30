<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Regression guard for the {tenant_slug} forgetParameter fix.
 *
 * Every tenant-scoped route group carries a leading {tenant_slug} prefix param.
 * The tenant-resolving middleware (TenantMiddleware / ResolveTenantForCustomer /
 * ResolveTenantForMechanic) MUST call forgetParameter('tenant_slug') after
 * binding the tenant — otherwise Laravel's positional dependency resolution
 * shifts the slug STRING into the controller's route-model argument (a typed
 * Vehicle/Invoice/ServiceLog), producing a TypeError (HTTP 500).
 *
 * Index routes never expose this bug (no model arg). So each guard below hits a
 * route with a {model} parameter — the exact shape that breaks without the fix.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class ForgetParameterTest extends TestCase
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

    public function test_tenant_guard_resolves_a_model_route_without_swallowing_the_slug(): void
    {
        $tenant = $this->makeTenant('tg-a');

        app()->instance('current_tenant', $tenant);
        $user = TenantUser::create([
            'name' => 'Admin',
            'email' => 'admin@tg.test',
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
        $vehicle = Vehicle::create([
            'registration_number' => 'REG-TG-1',
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2022,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 9000,
        ]);

        // GET app/{slug}/fleet/{vehicle} — FleetController@show(Vehicle $vehicle).
        // Without forgetParameter the slug 'tg-a' would land in $vehicle → 500.
        $this->actingAs($user, 'tenant')
            ->get("/app/tg-a/fleet/{$vehicle->id}")
            ->assertOk();
    }

    public function test_customer_guard_resolves_a_model_route_without_swallowing_the_slug(): void
    {
        $tenant = $this->makeTenant('cg-a');

        app()->instance('current_tenant', $tenant);
        $customer = Customer::create([
            'name' => 'Renter',
            'email' => 'renter@cg.test',
            'phone' => '0400000000',
            'licence_number' => 'LIC-'.uniqid(),
            'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '0411111111',
        ]);
        $customerUser = CustomerUser::create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'password' => 'secret123',
        ]);
        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT,
            'billing_period_start' => now()->toDateString(),
            'billing_period_end' => now()->addMonth()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'subtotal' => 10000,
            'total' => 10000,
            'paid_amount' => 0,
        ]);

        // GET portal/{slug}/invoices/{invoice} — showInvoice(Invoice $invoice).
        $this->actingAs($customerUser, 'customer')
            ->get("/portal/cg-a/invoices/{$invoice->id}")
            ->assertOk();
    }

    public function test_mechanic_guard_resolves_a_model_route_without_swallowing_the_slug(): void
    {
        $tenant = $this->makeTenant('mg-a');

        app()->instance('current_tenant', $tenant);
        $mechanic = Mechanic::create([
            'name' => 'Sam',
            'email' => 'sam@mg.test',
            'pin' => '1234',
            'password' => 'secret123',
            'is_active' => true,
        ]);
        $vehicle = Vehicle::create([
            'registration_number' => 'REG-MG-1',
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2022,
            'status' => Vehicle::STATUS_MAINTENANCE,
            'daily_rate' => 9000,
        ]);
        $log = ServiceLog::create([
            'vehicle_id' => $vehicle->id,
            'mechanic_id' => $mechanic->id,
            'status' => ServiceLog::STATUS_PENDING,
            'title' => 'Brake service',
            'labour_cost' => 3000,
            'total_cost' => 3000,
            'started_at' => now(),
        ]);

        // PUT mechanic/{slug}/logs/{log}/status — updateStatus(.., ServiceLog $log, ..).
        // This controller method declares NO tenant_slug param, so the slug would
        // be injected into $log without the fix → TypeError (500). The mechanic
        // owns the log, so MechanicPolicy::update passes.
        $this->actingAs($mechanic, 'mechanic')
            ->put("/mechanic/mg-a/logs/{$log->id}/status", [
                'status' => ServiceLog::STATUS_IN_PROGRESS,
            ])
            ->assertStatus(302);

        app()->instance('current_tenant', $tenant);
        $this->assertSame(ServiceLog::STATUS_IN_PROGRESS, $log->fresh()->status);
    }

    public function test_superadmin_guard_resolves_a_model_route_no_slug_involved(): void
    {
        // The super admin panel is platform-wide — it has NO {tenant_slug} prefix,
        // so forgetParameter does not apply. This proves the {tenant} model route
        // still binds correctly (no regression from the audit).
        Role::firstOrCreate(['name' => SuperAdmin::ROLE_PLATFORM_OWNER, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret1234',
            'role' => SuperAdmin::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);
        $admin->assignRole(SuperAdmin::ROLE_PLATFORM_OWNER);

        $tenant = $this->makeTenant('sa-target');

        // GET superadmin/tenants/{tenant} — {tenant} binds by slug (getRouteKeyName).
        $this->actingAs($admin, 'superadmin')
            ->get("/superadmin/tenants/{$tenant->slug}")
            ->assertOk();
    }
}
