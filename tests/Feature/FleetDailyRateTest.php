<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Client feedback: "daily rates me dollar huna chahye (with option to add in
 * decimal)". The Fleet create/edit forms were posting the raw number the user
 * typed straight to a column that stores CENTS, with no dollars→cents
 * conversion — typing "45" (meaning $45/day) stored 45 cents ($0.45/day).
 *
 * Confirmed live on the real database before the fix: a seeded vehicle sat at
 * $0.10/day. vehicles:audit-daily-rates recovers existing corrupted rows.
 */
class FleetDailyRateTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeUser(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    /**
     * The server has always stored daily_rate as CENTS, and still does — the
     * bug was entirely client-side (the Create/Edit forms sent the raw number
     * typed, without the dollars→cents conversion every other money field on
     * the same forms already used). This documents the fixed CONTRACT the
     * Vue form's transform() now honours: a request posts cents, e.g. "$45.50"
     * typed becomes 4550 before it ever reaches this endpoint.
     */
    public function test_creating_a_vehicle_stores_the_posted_cents_as_is(): void
    {
        $t = $this->makeTenant('rate-create');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->post("/app/{$t->slug}/fleet", [
            'registration_number' => 'RATE001',
            'make' => 'Toyota', 'model' => 'Camry', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 4550, // what the now-fixed form sends for "$45.50"
        ])->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $vehicle = Vehicle::where('registration_number', 'RATE001')->firstOrFail();

        $this->assertSame(4550, $vehicle->daily_rate);
    }

    /**
     * The edit form must now show the EXISTING cents value as dollars
     * (toDollars), not the raw cents — this is the prop the Vue page's
     * `toDollars(props.vehicle.daily_rate)` initial form value depends on.
     */
    public function test_the_edit_page_still_receives_the_rate_in_cents_for_the_form_to_convert(): void
    {
        $t = $this->makeTenant('rate-edit');
        $admin = $this->makeUser($t);

        app()->instance('current_tenant', $t);
        $vehicle = Vehicle::create([
            'registration_number' => 'RATE002', 'make' => 'Kia', 'model' => 'Cerato', 'year' => 2022,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 7000, // $70.00
        ]);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/fleet/{$vehicle->id}/edit")
            ->assertInertia(fn ($page) => $page->where('vehicle.daily_rate', 7000));

        $this->actingAs($admin, 'tenant')->put("/app/{$t->slug}/fleet/{$vehicle->id}", [
            'registration_number' => 'RATE002', 'make' => 'Kia', 'model' => 'Cerato', 'year' => 2022,
            'daily_rate' => 8225, // what the now-fixed form sends for "$82.25"
        ])->assertSessionHasNoErrors();

        $this->assertSame(8225, $vehicle->fresh()->daily_rate);
    }

    public function test_the_audit_command_finds_and_corrects_bug_victims(): void
    {
        $t = $this->makeTenant('rate-audit');
        app()->instance('current_tenant', $t);

        $corrupted = Vehicle::create([
            'registration_number' => 'BUG001', 'make' => 'Hyundai', 'model' => 'i30', 'year' => 2021,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 45, // the bug: $45 typed, 45 cents stored
        ]);
        $genuine = Vehicle::create([
            'registration_number' => 'OK001', 'make' => 'Mazda', 'model' => '3', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 9500, // a real $95.00/day rate
        ]);

        app()->forgetInstance('current_tenant');

        // Dry run (default) lists but changes nothing.
        $this->artisan('vehicles:audit-daily-rates', ['--tenant' => $t->slug])->assertSuccessful();
        $this->assertSame(45, $corrupted->fresh()->daily_rate);

        $this->artisan('vehicles:audit-daily-rates', ['--tenant' => $t->slug, '--fix' => true])->assertSuccessful();

        $this->assertSame(4500, $corrupted->fresh()->daily_rate, 'the flagged row is corrected ×100');
        $this->assertSame(9500, $genuine->fresh()->daily_rate, 'a genuine rate above the threshold is left alone');
    }

    public function test_the_audit_command_never_touches_other_tenants(): void
    {
        $a = $this->makeTenant('rate-a');
        app()->instance('current_tenant', $a);
        $vehicleA = Vehicle::create([
            'registration_number' => 'TA001', 'make' => 'Toyota', 'model' => 'Yaris', 'year' => 2020,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 30,
        ]);

        $b = $this->makeTenant('rate-b');
        app()->instance('current_tenant', $b);
        $vehicleB = Vehicle::create([
            'registration_number' => 'TB001', 'make' => 'Honda', 'model' => 'Civic', 'year' => 2020,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 30,
        ]);

        app()->forgetInstance('current_tenant');

        $this->artisan('vehicles:audit-daily-rates', ['--tenant' => $a->slug, '--fix' => true])->assertSuccessful();

        $this->assertSame(3000, $vehicleA->fresh()->daily_rate);
        $this->assertSame(30, $vehicleB->fresh()->daily_rate, "tenant B's data must be untouched when auditing tenant A");
    }
}
