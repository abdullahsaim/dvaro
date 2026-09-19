<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use App\Modules\Workshop\Services\VehicleLookupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Mechanic portal — manual number-plate lookup + the id-based vehicle page that
 * QR scans now redirect to. Tenant-isolated. Real HTTP stack.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class MechanicPlateSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
    }

    private function makeMechanic(Tenant $tenant): Mechanic
    {
        app()->instance('current_tenant', $tenant);

        return Mechanic::create([
            'name' => 'Sam',
            'email' => 'sam@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'is_active' => true,
        ]);
    }

    private function makeVehicle(Tenant $tenant, string $rego, ?string $token = null): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        return Vehicle::create([
            'registration_number' => $rego,
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2022,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 9000,
            'qr_code_token' => $token,
        ]);
    }

    public function test_plate_normalisation(): void
    {
        $this->assertSame('1ABC234', VehicleLookupService::normalizePlate(' 1abc-234 '));
        $this->assertSame('1ABC234', VehicleLookupService::normalizePlate('1 A.B.C 2 3 4'));
    }

    public function test_single_match_redirects_to_vehicle_page_ignoring_spacing_and_case(): void
    {
        $t = $this->makeTenant('plate-a');
        $mechanic = $this->makeMechanic($t);
        $vehicle = $this->makeVehicle($t, '1ABC-234');

        foreach (['1abc 234', '1ABC234', '1-a-b-c-2-3-4'] as $query) {
            $this->actingAs($mechanic, 'mechanic')
                ->get('/mechanic/plate-a/vehicles/search?plate='.urlencode($query))
                ->assertRedirect("/mechanic/plate-a/vehicles/{$vehicle->id}");
        }
    }

    public function test_multiple_matches_list_exact_first(): void
    {
        $t = $this->makeTenant('plate-b');
        $mechanic = $this->makeMechanic($t);
        $this->makeVehicle($t, 'XYZ 1000');
        $this->makeVehicle($t, 'XYZ 100');

        $this->actingAs($mechanic, 'mechanic')
            ->get('/mechanic/plate-b/vehicles/search?plate=xyz100')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workshop/Mechanic/VehicleSearch')
                ->has('results', 2)
                ->where('results.0.registration_number', 'XYZ 100'));
    }

    public function test_no_match_and_too_short_render_the_search_page(): void
    {
        $t = $this->makeTenant('plate-c');
        $mechanic = $this->makeMechanic($t);
        $this->makeVehicle($t, 'AAA111');

        $this->actingAs($mechanic, 'mechanic')
            ->get('/mechanic/plate-c/vehicles/search?plate=zzz999')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('results', 0)->where('tooShort', false));

        $this->actingAs($mechanic, 'mechanic')
            ->get('/mechanic/plate-c/vehicles/search?plate=a')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('results', 0)->where('tooShort', true));
    }

    public function test_other_tenants_plates_are_never_found_or_viewable(): void
    {
        $a = $this->makeTenant('plate-d1');
        $mechanicA = $this->makeMechanic($a);

        $b = $this->makeTenant('plate-d2');
        $theirs = $this->makeVehicle($b, 'SECRET9');

        $this->actingAs($mechanicA, 'mechanic')
            ->get('/mechanic/plate-d1/vehicles/search?plate=SECRET9')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('results', 0));

        $this->actingAs($mechanicA, 'mechanic')
            ->get("/mechanic/plate-d1/vehicles/{$theirs->id}")
            ->assertNotFound();

        $this->actingAs($mechanicA, 'mechanic')
            ->post('/mechanic/plate-d1/logs', ['vehicle_id' => $theirs->id, 'title' => 'Sneaky'])
            ->assertNotFound();

        app()->instance('current_tenant', $b);
        $this->assertSame(0, ServiceLog::count());
    }

    public function test_vehicle_without_qr_can_be_viewed_and_logged(): void
    {
        $t = $this->makeTenant('plate-e');
        $mechanic = $this->makeMechanic($t);
        $vehicle = $this->makeVehicle($t, 'NOQR1'); // no qr_code_token

        $this->actingAs($mechanic, 'mechanic')
            ->get("/mechanic/plate-e/vehicles/{$vehicle->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workshop/Mechanic/ScanResult')
                ->where('vehicle.id', $vehicle->id));

        $this->actingAs($mechanic, 'mechanic')
            ->post('/mechanic/plate-e/logs', [
                'vehicle_id' => $vehicle->id,
                'title' => 'Oil change',
                'labour_cost' => 5000,
            ])
            ->assertRedirect("/mechanic/plate-e/vehicles/{$vehicle->id}");

        app()->instance('current_tenant', $t);
        $this->assertSame(1, ServiceLog::where('vehicle_id', $vehicle->id)->count());
        $this->assertSame(Vehicle::STATUS_MAINTENANCE, $vehicle->fresh()->status);
    }

    public function test_log_requires_a_vehicle_reference(): void
    {
        $t = $this->makeTenant('plate-f');
        $mechanic = $this->makeMechanic($t);

        $this->actingAs($mechanic, 'mechanic')
            ->post('/mechanic/plate-f/logs', ['title' => 'Orphan'])
            ->assertSessionHasErrors(['vehicle_id', 'token']);
    }

    public function test_qr_token_route_still_resolves_to_the_vehicle_page(): void
    {
        $t = $this->makeTenant('plate-g');
        $mechanic = $this->makeMechanic($t);
        $vehicle = $this->makeVehicle($t, 'QR1', 'tok-plate-g');

        $this->actingAs($mechanic, 'mechanic')
            ->get('/mechanic/plate-g/vehicle/tok-plate-g')
            ->assertRedirect("/mechanic/plate-g/vehicles/{$vehicle->id}");
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $t = $this->makeTenant('plate-h');
        $vehicle = $this->makeVehicle($t, 'GUEST1');

        $this->get('/mechanic/plate-h/vehicles/search?plate=GUEST1')->assertRedirect();
        $this->get("/mechanic/plate-h/vehicles/{$vehicle->id}")->assertRedirect();
    }
}
