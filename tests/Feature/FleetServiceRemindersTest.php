<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\FleetReminder;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\FleetReminderService;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use Tests\TestCase;

/**
 * Client feedback #6 — odometer tracking (append-only, never backwards),
 * service schedule by months OR km (scheduled-service reset), and the daily
 * fleet reminder digest to ALL staff (due soon + overdue, once per due value).
 *
 * Email goes through the tenant's provider — 'log' by default — so every send
 * is a NotificationLog row we can count. DatabaseTransactions (rolls back) —
 * never RefreshDatabase / migrate:fresh.
 */
class FleetServiceRemindersTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $settings = []): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'settings' => $settings ?: null,
        ]);

        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeStaff(Tenant $tenant, string $role, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => ucfirst($role),
            'email' => $email,
            'password' => 'secret123',
            'role' => $role,
        ]);
    }

    private function makeVehicle(Tenant $tenant, string $rego, array $attrs = []): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        $vehicle = new Vehicle(array_merge([
            'registration_number' => $rego,
            'make' => 'Toyota',
            'model' => 'Camry',
            'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 8000,
        ], $attrs));
        $vehicle->applyServiceSchedule()->save();

        return $vehicle;
    }

    private function setOdometer(Vehicle $vehicle, int $km): void
    {
        $vehicle->forceFill(['current_odometer' => $km])->save();
    }

    private function digestCount(Tenant $tenant): int
    {
        app()->instance('current_tenant', $tenant);

        return NotificationLog::where('event_type', FleetReminderService::EVENT_TYPE)->count();
    }

    private function sweep(Tenant $tenant): int
    {
        app()->instance('current_tenant', $tenant);

        return app(FleetReminderService::class)->sweepTenant($tenant->fresh());
    }

    // ── Odometer ────────────────────────────────────────────────────────────

    public function test_staff_record_reading_appends_history_and_rejects_backwards(): void
    {
        $t = $this->makeTenant('odo-a');
        $admin = $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@odo-a.test');
        $vehicle = $this->makeVehicle($t, 'ODO1');

        $this->actingAs($admin, 'tenant')
            ->post("/app/odo-a/fleet/{$vehicle->id}/odometer", ['reading' => 42000])
            ->assertSessionHasNoErrors();

        $this->actingAs($admin, 'tenant')
            ->post("/app/odo-a/fleet/{$vehicle->id}/odometer", ['reading' => 41000])
            ->assertSessionHasErrors('reading');

        app()->instance('current_tenant', $t);
        $this->assertSame(42000, $vehicle->fresh()->current_odometer);
        $this->assertSame([42000], OdometerReading::where('vehicle_id', $vehicle->id)->pluck('reading')->all());

        $reading = OdometerReading::first();
        $this->assertSame(OdometerReading::SOURCE_MANUAL, $reading->source);
        $this->assertSame((int) $admin->id, $reading->recorded_by_id);
    }

    public function test_odometer_history_is_append_only(): void
    {
        $t = $this->makeTenant('odo-b');
        $vehicle = $this->makeVehicle($t, 'ODO2');
        $reading = OdometerReading::create([
            'vehicle_id' => $vehicle->id, 'reading' => 10, 'source' => OdometerReading::SOURCE_MANUAL, 'recorded_at' => now(),
        ]);

        $this->expectException(LogicException::class);
        $reading->update(['reading' => 5]);
    }

    public function test_create_vehicle_records_starting_odometer_and_derives_schedule(): void
    {
        $t = $this->makeTenant('odo-c');
        $admin = $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@odo-c.test');

        $this->actingAs($admin, 'tenant')->post('/app/odo-c/fleet', [
            'registration_number' => 'NEW1',
            'make' => 'Kia', 'model' => 'Cerato', 'year' => 2024,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 7000,
            'last_service_date' => '2026-06-01',
            'service_interval_months' => 6,
            'service_interval_km' => 10000,
            'current_odometer' => 25000,
            'last_service_odometer' => 20000,
        ])->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $vehicle = Vehicle::where('registration_number', 'NEW1')->firstOrFail();

        $this->assertSame(25000, $vehicle->current_odometer);
        $this->assertSame(30000, $vehicle->next_service_km);
        $this->assertSame('2026-12-01', $vehicle->next_service_due->toDateString());
        $this->assertSame(1, OdometerReading::where('vehicle_id', $vehicle->id)->count());
    }

    public function test_last_service_odometer_cannot_exceed_current(): void
    {
        $t = $this->makeTenant('odo-d');
        $admin = $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@odo-d.test');

        $this->actingAs($admin, 'tenant')->post('/app/odo-d/fleet', [
            'registration_number' => 'BAD1', 'make' => 'Kia', 'model' => 'Rio', 'year' => 2024,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 7000,
            'current_odometer' => 1000, 'last_service_odometer' => 5000,
        ])->assertSessionHasErrors('last_service_odometer');
    }

    // ── Service logs ────────────────────────────────────────────────────────

    private function makeMechanic(Tenant $tenant): Mechanic
    {
        app()->instance('current_tenant', $tenant);

        return Mechanic::create([
            'name' => 'Sam', 'email' => 'sam@'.$tenant->slug.'.test', 'password' => 'secret123', 'is_active' => true,
        ]);
    }

    public function test_service_log_odometer_is_recorded_and_a_lower_one_is_flagged_not_recorded(): void
    {
        $t = $this->makeTenant('odo-e');
        $mechanic = $this->makeMechanic($t);
        $vehicle = $this->makeVehicle($t, 'SVC1');
        $this->setOdometer($vehicle, 50000);

        $this->actingAs($mechanic, 'mechanic')->post('/mechanic/odo-e/logs', [
            'vehicle_id' => $vehicle->id, 'title' => 'Oil', 'odometer_reading' => 51000,
        ])->assertSessionHasNoErrors();

        $this->actingAs($mechanic, 'mechanic')->post('/mechanic/odo-e/logs', [
            'vehicle_id' => $vehicle->id, 'title' => 'Typo', 'odometer_reading' => 5100,
        ])->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $this->assertSame(51000, $vehicle->fresh()->current_odometer);

        $readings = OdometerReading::where('vehicle_id', $vehicle->id)->get();
        $this->assertCount(1, $readings);
        $this->assertSame(OdometerReading::SOURCE_SERVICE_LOG, $readings->first()->source);
        $this->assertSame((int) $mechanic->id, $readings->first()->recorded_by_id);

        $this->assertTrue(ServiceLog::where('title', 'Typo')->firstOrFail()->odometer_ignored);
        $this->assertFalse(ServiceLog::where('title', 'Oil')->firstOrFail()->odometer_ignored);
    }

    public function test_completing_a_scheduled_service_resets_the_schedule_but_a_repair_does_not(): void
    {
        $t = $this->makeTenant('odo-f');
        $mechanic = $this->makeMechanic($t);
        $vehicle = $this->makeVehicle($t, 'SVC2', [
            'service_interval_months' => 6,
            'service_interval_km' => 10000,
            'last_service_date' => '2026-01-01',
            'last_service_odometer' => 40000,
        ]);
        $this->setOdometer($vehicle, 49000);

        // A repair: completes, schedule untouched.
        $this->actingAs($mechanic, 'mechanic')->post('/mechanic/odo-f/logs', [
            'vehicle_id' => $vehicle->id, 'title' => 'Brakes', 'odometer_reading' => 49500,
        ]);
        app()->instance('current_tenant', $t);
        $repair = ServiceLog::where('title', 'Brakes')->firstOrFail();
        $this->actingAs($mechanic, 'mechanic')->put("/mechanic/odo-f/logs/{$repair->id}/status", ['status' => 'completed']);

        app()->instance('current_tenant', $t);
        $this->assertSame(50000, $vehicle->fresh()->next_service_km);

        // A scheduled service: schedule resets from its odometer + today.
        $this->actingAs($mechanic, 'mechanic')->post('/mechanic/odo-f/logs', [
            'vehicle_id' => $vehicle->id, 'title' => 'Major service', 'odometer_reading' => 50100, 'is_scheduled_service' => true,
        ]);
        app()->instance('current_tenant', $t);
        $service = ServiceLog::where('title', 'Major service')->firstOrFail();
        $this->actingAs($mechanic, 'mechanic')->put("/mechanic/odo-f/logs/{$service->id}/status", ['status' => 'completed']);

        app()->instance('current_tenant', $t);
        $fresh = $vehicle->fresh();
        $this->assertSame(50100, $fresh->last_service_odometer);
        $this->assertSame(60100, $fresh->next_service_km);
        $this->assertSame(today()->toDateString(), $fresh->last_service_date->toDateString());
        $this->assertSame(today()->addMonthsNoOverflow(6)->toDateString(), $fresh->next_service_due->toDateString());
    }

    // ── Reminders ───────────────────────────────────────────────────────────

    public function test_due_soon_digest_goes_to_all_staff_once_then_overdue_once(): void
    {
        $t = $this->makeTenant('rem-a');
        $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@rem-a.test');
        $this->makeStaff($t, TenantUser::ROLE_STAFF, 'staff@rem-a.test');
        $this->makeStaff($t, TenantUser::ROLE_ACCOUNTS, 'accounts@rem-a.test');
        $this->makeVehicle($t, 'REGO1', ['registration_expiry' => today()->addDays(10)->toDateString()]);
        $this->makeVehicle($t, 'FINE1', ['registration_expiry' => today()->addDays(200)->toDateString()]);

        $this->assertSame(1, $this->sweep($t));
        $this->assertSame(3, $this->digestCount($t)); // one digest per staff member

        app()->instance('current_tenant', $t);
        $log = NotificationLog::where('event_type', FleetReminderService::EVENT_TYPE)->firstOrFail();
        $this->assertStringContainsString('REGO1', $log->body);
        $this->assertStringNotContainsString('FINE1', $log->body);

        // Same day again → nothing new.
        $this->assertSame(0, $this->sweep($t));
        $this->assertSame(3, $this->digestCount($t));

        // After the date passes → one overdue reminder, then silence.
        $this->travel(11)->days();
        $this->assertSame(1, $this->sweep($t));
        $this->assertSame(6, $this->digestCount($t));
        $this->assertSame(0, $this->sweep($t));

        app()->instance('current_tenant', $t);
        $this->assertEqualsCanonicalizing(
            [FleetReminder::STAGE_DUE_SOON, FleetReminder::STAGE_OVERDUE],
            FleetReminder::pluck('stage')->all(),
        );
    }

    public function test_service_by_km_reminds_due_soon_then_overdue(): void
    {
        $t = $this->makeTenant('rem-b', ['fleet_reminder_km' => 500]);
        $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@rem-b.test');
        $vehicle = $this->makeVehicle($t, 'KM1', ['service_interval_km' => 10000, 'last_service_odometer' => 20000]);

        $this->setOdometer($vehicle, 29000); // 1,000 km to go > 500 lead
        $this->assertSame(0, $this->sweep($t));

        $this->setOdometer($vehicle, 29600); // 400 km to go
        $this->assertSame(1, $this->sweep($t));

        $this->setOdometer($vehicle, 30050); // past 30,000
        $this->assertSame(1, $this->sweep($t));
        $this->assertSame(0, $this->sweep($t));

        app()->instance('current_tenant', $t);
        $this->assertSame(
            [FleetReminder::KIND_SERVICE_KM],
            FleetReminder::distinct()->pluck('kind')->all(),
        );
    }

    public function test_renewed_date_re_arms_the_reminder(): void
    {
        $t = $this->makeTenant('rem-c');
        $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@rem-c.test');
        $vehicle = $this->makeVehicle($t, 'INS1', ['insurance_expiry' => today()->addDays(5)->toDateString()]);

        $this->assertSame(1, $this->sweep($t));

        // Renewed, but the new date is (unusually) also inside the window.
        $vehicle->update(['insurance_expiry' => today()->addDays(20)->toDateString()]);
        $this->assertSame(1, $this->sweep($t));
    }

    public function test_disabled_reminders_or_email_send_nothing(): void
    {
        $off = $this->makeTenant('rem-d', ['fleet_reminders_enabled' => false]);
        $this->makeStaff($off, TenantUser::ROLE_ADMIN, 'admin@rem-d.test');
        $this->makeVehicle($off, 'OFF1', ['registration_expiry' => today()->toDateString()]);
        $this->assertSame(0, $this->sweep($off));

        $noEmail = $this->makeTenant('rem-e', ['notify_email_enabled' => false]);
        $this->makeStaff($noEmail, TenantUser::ROLE_ADMIN, 'admin@rem-e.test');
        $this->makeVehicle($noEmail, 'OFF2', ['registration_expiry' => today()->toDateString()]);
        $this->assertSame(0, $this->sweep($noEmail));

        $this->assertSame(0, $this->digestCount($off) + $this->digestCount($noEmail));
    }

    public function test_full_sweep_keeps_tenants_isolated(): void
    {
        $a = $this->makeTenant('rem-f1');
        $this->makeStaff($a, TenantUser::ROLE_ADMIN, 'admin@rem-f1.test');
        $this->makeVehicle($a, 'AAA111', ['registration_expiry' => today()->addDays(3)->toDateString()]);

        $b = $this->makeTenant('rem-f2');
        $this->makeStaff($b, TenantUser::ROLE_ADMIN, 'admin@rem-f2.test');
        $this->makeVehicle($b, 'BBB222', ['registration_expiry' => today()->addDays(3)->toDateString()]);

        app()->forgetInstance('current_tenant');
        app(FleetReminderService::class)->sweep();

        app()->instance('current_tenant', $a);
        $bodyA = NotificationLog::where('event_type', FleetReminderService::EVENT_TYPE)->firstOrFail();
        $this->assertSame('admin@rem-f1.test', $bodyA->recipient);
        $this->assertStringContainsString('AAA111', $bodyA->body);
        $this->assertStringNotContainsString('BBB222', $bodyA->body);

        app()->instance('current_tenant', $b);
        $bodyB = NotificationLog::where('event_type', FleetReminderService::EVENT_TYPE)->firstOrFail();
        $this->assertStringContainsString('BBB222', $bodyB->body);
        $this->assertStringNotContainsString('AAA111', $bodyB->body);
    }

    // ── Fleet list + settings ───────────────────────────────────────────────

    public function test_fleet_list_flags_km_due_vehicles(): void
    {
        $t = $this->makeTenant('rem-g');
        $admin = $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@rem-g.test');
        $vehicle = $this->makeVehicle($t, 'KMLIST', ['service_interval_km' => 10000, 'last_service_odometer' => 0]);
        $this->setOdometer($vehicle, 10200);
        $this->makeVehicle($t, 'CALM');

        $this->actingAs($admin, 'tenant')
            ->get('/app/rem-g/fleet?expiring=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('vehicles.data', 1)
                ->where('vehicles.data.0.registration_number', 'KMLIST')
                ->where('vehicles.data.0.service_state', 'overdue'));
    }

    public function test_admin_can_save_fleet_reminder_settings(): void
    {
        $t = $this->makeTenant('rem-h');
        $admin = $this->makeStaff($t, TenantUser::ROLE_ADMIN, 'admin@rem-h.test');

        $this->actingAs($admin, 'tenant')->put('/app/rem-h/notifications/settings', [
            'email_provider' => 'log',
            'sms_provider' => 'log',
            'notify_email_enabled' => true,
            'notify_sms_enabled' => false,
            'notify_whatsapp_enabled' => false,
            'fleet_reminders_enabled' => true,
            'fleet_reminder_days' => 45,
            'fleet_reminder_km' => 2000,
        ])->assertSessionHasNoErrors();

        $settings = $t->fresh()->settings;
        $this->assertSame(45, $settings['fleet_reminder_days']);
        $this->assertSame(2000, $settings['fleet_reminder_km']);

        app()->instance('current_tenant', $t->fresh());
        $this->assertSame(45, Vehicle::reminderLeadDays());
        $this->assertSame(2000, Vehicle::reminderLeadKm());
    }
}
