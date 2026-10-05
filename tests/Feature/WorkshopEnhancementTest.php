<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use App\Modules\Workshop\Models\ServiceLogDocument;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Workshop module enhancements: future-dated scheduling (the vehicle stays in
 * service until work actually starts), document attachments, and the
 * maintenance start/complete notifications that previously went unlistened.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class WorkshopEnhancementTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeAdmin(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@test.au',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function makeMechanic(Tenant $tenant, array $attrs = []): Mechanic
    {
        app()->instance('current_tenant', $tenant);

        return Mechanic::create(array_merge([
            'name' => 'Mechanic '.uniqid(), 'email' => 'mech-'.uniqid().'@test.au',
            'pin' => '1234', 'password' => 'secret123', 'is_active' => true,
        ], $attrs));
    }

    private function makeVehicle(Tenant $tenant, string $rego): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        return Vehicle::create([
            'registration_number' => $rego, 'make' => 'Toyota', 'model' => 'Hilux',
            'year' => 2022, 'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 9000,
        ]);
    }

    // ── Scheduling ───────────────────────────────────────────────────────────

    public function test_scheduling_a_service_does_not_touch_the_vehicle(): void
    {
        $tenant = $this->makeTenant('wk-sched');
        $admin = $this->makeAdmin($tenant);
        $vehicle = $this->makeVehicle($tenant, 'SCH001');
        $mechanic = $this->makeMechanic($tenant);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$tenant->slug}/workshop/schedule", [
                'vehicle_id' => $vehicle->id,
                'mechanic_id' => $mechanic->id,
                'title' => 'Annual service',
                'scheduled_for' => now()->addWeek()->toDateTimeString(),
            ])
            ->assertRedirect();

        $this->assertSame(Vehicle::STATUS_AVAILABLE, $vehicle->fresh()->status, 'booked ahead — vehicle stays in service');

        $log = ServiceLog::where('vehicle_id', $vehicle->id)->firstOrFail();
        $this->assertSame(ServiceLog::STATUS_PENDING, $log->status);
        $this->assertNull($log->started_at);
        $this->assertNotNull($log->scheduled_for);
        $this->assertTrue($log->isUpcoming());
    }

    public function test_starting_a_scheduled_job_puts_the_vehicle_into_maintenance(): void
    {
        $tenant = $this->makeTenant('wk-start');
        $admin = $this->makeAdmin($tenant);
        $vehicle = $this->makeVehicle($tenant, 'SCH002');
        $mechanic = $this->makeMechanic($tenant);

        $this->actingAs($admin, 'tenant')->post("/app/{$tenant->slug}/workshop/schedule", [
            'vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id,
            'title' => 'Brake service', 'scheduled_for' => now()->addDay()->toDateTimeString(),
        ]);

        $log = ServiceLog::where('vehicle_id', $vehicle->id)->firstOrFail();

        $this->actingAs($mechanic, 'mechanic')
            ->put("/mechanic/{$tenant->slug}/logs/{$log->id}/status", ['status' => 'in_progress'])
            ->assertRedirect();

        $fresh = $log->fresh();
        $this->assertSame(ServiceLog::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNotNull($fresh->started_at);
        $this->assertFalse($fresh->isUpcoming());
        $this->assertSame(Vehicle::STATUS_MAINTENANCE, $vehicle->fresh()->status);
    }

    public function test_completing_a_scheduled_job_still_returns_the_vehicle_to_available(): void
    {
        $tenant = $this->makeTenant('wk-complete-sched');
        $admin = $this->makeAdmin($tenant);
        $vehicle = $this->makeVehicle($tenant, 'SCH003');
        $mechanic = $this->makeMechanic($tenant);

        $this->actingAs($admin, 'tenant')->post("/app/{$tenant->slug}/workshop/schedule", [
            'vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id,
            'title' => 'Oil change', 'scheduled_for' => now()->addDay()->toDateTimeString(),
        ]);
        $log = ServiceLog::where('vehicle_id', $vehicle->id)->firstOrFail();

        $this->actingAs($mechanic, 'mechanic')->put("/mechanic/{$tenant->slug}/logs/{$log->id}/status", ['status' => 'in_progress']);
        $this->actingAs($mechanic, 'mechanic')->put("/mechanic/{$tenant->slug}/logs/{$log->id}/status", ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame(Vehicle::STATUS_AVAILABLE, $vehicle->fresh()->status);
        $this->assertSame(ServiceLog::STATUS_COMPLETED, $log->fresh()->status);
    }

    public function test_scheduling_rejects_a_vehicle_from_another_tenant(): void
    {
        $tenantA = $this->makeTenant('wk-cross-a');
        $vehicleA = $this->makeVehicle($tenantA, 'XA001');

        $tenantB = $this->makeTenant('wk-cross-b');
        $adminB = $this->makeAdmin($tenantB);
        $mechanicB = $this->makeMechanic($tenantB);

        $this->actingAs($adminB, 'tenant')
            ->post("/app/{$tenantB->slug}/workshop/schedule", [
                'vehicle_id' => $vehicleA->id, // belongs to tenant A
                'mechanic_id' => $mechanicB->id,
                'title' => 'Should fail',
                'scheduled_for' => now()->addDay()->toDateTimeString(),
            ])
            ->assertSessionHasErrors('vehicle_id');
    }

    // ── Documents ────────────────────────────────────────────────────────────

    private function activeJob(Tenant $tenant, Mechanic $mechanic, Vehicle $vehicle): ServiceLog
    {
        app()->instance('current_tenant', $tenant);

        return ServiceLog::create([
            'vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id,
            'status' => ServiceLog::STATUS_PENDING, 'title' => 'Job', 'started_at' => now(),
        ]);
    }

    public function test_a_mechanic_can_attach_and_an_admin_can_download_a_document(): void
    {
        $tenant = $this->makeTenant('wk-doc');
        $admin = $this->makeAdmin($tenant);
        $mechanic = $this->makeMechanic($tenant);
        $vehicle = $this->makeVehicle($tenant, 'DOC001');
        $log = $this->activeJob($tenant, $mechanic, $vehicle);

        $file = UploadedFile::fake()->image('damage.jpg', 10, 10)->size(100);

        $this->actingAs($mechanic, 'mechanic')
            ->post("/mechanic/{$tenant->slug}/logs/{$log->id}/documents", ['file' => $file])
            ->assertRedirect();

        $document = ServiceLogDocument::where('service_log_id', $log->id)->firstOrFail();
        $this->assertSame(ServiceLogDocument::UPLOADED_BY_MECHANIC, $document->uploaded_by_type);
        $this->assertSame('damage.jpg', $document->original_name);

        $this->actingAs($admin, 'tenant')
            ->get("/app/{$tenant->slug}/workshop/{$log->id}/documents/{$document->id}")
            ->assertRedirect(); // redirect()->away() to the signed temporary URL
    }

    public function test_a_mechanic_cannot_attach_a_document_to_another_mechanics_job_unless_senior(): void
    {
        $tenant = $this->makeTenant('wk-doc-perm');
        $owner = $this->makeMechanic($tenant);
        $other = $this->makeMechanic($tenant);
        $vehicle = $this->makeVehicle($tenant, 'DOC002');
        $log = $this->activeJob($tenant, $owner, $vehicle);

        $this->actingAs($other, 'mechanic')
            ->post("/mechanic/{$tenant->slug}/logs/{$log->id}/documents", [
                'file' => UploadedFile::fake()->image('x.jpg')->size(10),
            ])
            ->assertForbidden();
    }

    public function test_an_admin_can_remove_a_document(): void
    {
        $tenant = $this->makeTenant('wk-doc-remove');
        $admin = $this->makeAdmin($tenant);
        $mechanic = $this->makeMechanic($tenant);
        $vehicle = $this->makeVehicle($tenant, 'DOC003');
        $log = $this->activeJob($tenant, $mechanic, $vehicle);

        app()->instance('current_tenant', $tenant);
        $document = ServiceLogDocument::create([
            'service_log_id' => $log->id, 'path' => 'tenants/'.$tenant->id.'/workshop/fake.jpg',
            'original_name' => 'fake.jpg', 'uploaded_by_type' => 'mechanic', 'uploaded_by_id' => $mechanic->id,
        ]);

        $this->actingAs($admin, 'tenant')
            ->delete("/app/{$tenant->slug}/workshop/{$log->id}/documents/{$document->id}")
            ->assertRedirect();

        $this->assertNull(ServiceLogDocument::find($document->id));
    }

    // ── Notifications ────────────────────────────────────────────────────────

    public function test_opening_and_completing_a_job_notifies_the_admin(): void
    {
        $tenant = $this->makeTenant('wk-notify');
        $mechanic = $this->makeMechanic($tenant);
        $this->makeAdmin($tenant); // notifyAdmin() resolves the first tenant_admin
        $vehicle = $this->makeVehicle($tenant, 'NOT001');

        $this->actingAs($mechanic, 'mechanic')
            ->post("/mechanic/{$tenant->slug}/logs", [
                'vehicle_id' => $vehicle->id,
                'title' => 'Transmission check',
            ])
            ->assertRedirect();

        app()->instance('current_tenant', $tenant);
        $this->assertTrue(NotificationLog::where('event_type', 'workshop.maintenance_started')->exists());

        $log = ServiceLog::where('vehicle_id', $vehicle->id)->firstOrFail();
        $this->actingAs($mechanic, 'mechanic')
            ->put("/mechanic/{$tenant->slug}/logs/{$log->id}/status", ['status' => 'completed']);

        app()->instance('current_tenant', $tenant);
        $this->assertTrue(NotificationLog::where('event_type', 'workshop.maintenance_completed')->exists());
    }

    // ── Analytics snapshot ───────────────────────────────────────────────────

    public function test_the_workshop_report_includes_an_open_status_mix_and_upcoming_count(): void
    {
        $tenant = $this->makeTenant('wk-report');
        $mechanic = $this->makeMechanic($tenant);
        $vehicle1 = $this->makeVehicle($tenant, 'REP001');
        $vehicle2 = $this->makeVehicle($tenant, 'REP002');

        $this->activeJob($tenant, $mechanic, $vehicle1); // status=pending, started_at set
        app()->instance('current_tenant', $tenant);
        ServiceLog::create([
            'vehicle_id' => $vehicle2->id, 'mechanic_id' => $mechanic->id,
            'status' => ServiceLog::STATUS_PENDING, 'title' => 'Future job',
            'scheduled_for' => now()->addDays(3),
        ]);

        $report = app(ReportingService::class)->workshopPerformance(now()->subMonth(), now()->addMonth());

        // Both logs are status=pending (one active, one booked ahead) — the
        // open-status snapshot counts every non-completed job regardless.
        $this->assertSame(2, $report['open_by_status'][ServiceLog::STATUS_PENDING]);
        $this->assertSame(1, $report['upcoming_count']);
    }
}
