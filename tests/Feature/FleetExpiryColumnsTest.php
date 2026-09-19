<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Fleet list — Rego Expiry + Next Service columns, server-computed badge state,
 * "Expiring soon" filter and whitelisted date sorting. Real HTTP stack.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class FleetExpiryColumnsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
    }

    private function makeUser(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin',
            'email' => 'admin@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    private function makeVehicle(Tenant $tenant, string $rego, ?string $regoExpiry, ?string $serviceDue): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        return Vehicle::create([
            'registration_number' => $rego,
            'make' => 'Toyota',
            'model' => 'Corolla',
            'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 8000,
            'registration_expiry' => $regoExpiry,
            'next_service_due' => $serviceDue,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function rows($response): array
    {
        return $response->viewData('page')['props']['vehicles']['data'];
    }

    private function seedFleet(Tenant $t): void
    {
        $this->makeVehicle($t, 'OVERDUE1', today()->subDays(3)->toDateString(), today()->addYear()->toDateString());
        $this->makeVehicle($t, 'SOON1', today()->addYear()->toDateString(), today()->addDays(10)->toDateString());
        $this->makeVehicle($t, 'FINE1', today()->addDays(200)->toDateString(), today()->addDays(90)->toDateString());
        $this->makeVehicle($t, 'NODATES', null, null);
    }

    public function test_index_exposes_dates_and_expiry_states(): void
    {
        $t = $this->makeTenant('fleet-x1');
        $user = $this->makeUser($t);
        $this->seedFleet($t);

        $rows = collect($this->rows($this->actingAs($user, 'tenant')->get('/app/fleet-x1/fleet')->assertOk()))
            ->keyBy('registration_number');

        $this->assertSame('overdue', $rows['OVERDUE1']['registration_state']);
        $this->assertSame('ok', $rows['OVERDUE1']['service_state']);
        $this->assertSame('due_soon', $rows['SOON1']['service_state']);
        $this->assertSame('ok', $rows['FINE1']['registration_state']);
        $this->assertNull($rows['NODATES']['registration_state']);
        $this->assertNull($rows['NODATES']['service_state']);
    }

    public function test_expiring_filter_includes_overdue_and_due_soon_only(): void
    {
        $t = $this->makeTenant('fleet-x2');
        $user = $this->makeUser($t);
        $this->seedFleet($t);

        $response = $this->actingAs($user, 'tenant')->get('/app/fleet-x2/fleet?expiring=1')->assertOk();
        $regos = collect($this->rows($response))->pluck('registration_number')->sort()->values()->all();

        $this->assertSame(['OVERDUE1', 'SOON1'], $regos);
        $this->assertSame(2, $response->viewData('page')['props']['expiringCount']);
    }

    public function test_sorting_by_next_service_puts_empty_dates_last_in_both_directions(): void
    {
        $t = $this->makeTenant('fleet-x3');
        $user = $this->makeUser($t);
        $this->seedFleet($t);

        $asc = collect($this->rows($this->actingAs($user, 'tenant')->get('/app/fleet-x3/fleet?sort=next_service_due')))
            ->pluck('registration_number')->all();
        $this->assertSame(['SOON1', 'FINE1', 'OVERDUE1', 'NODATES'], $asc);

        $desc = collect($this->rows($this->actingAs($user, 'tenant')->get('/app/fleet-x3/fleet?sort=next_service_due&direction=desc')))
            ->pluck('registration_number')->all();
        $this->assertSame(['OVERDUE1', 'FINE1', 'SOON1', 'NODATES'], $desc);
    }

    public function test_unknown_sort_column_is_ignored(): void
    {
        $t = $this->makeTenant('fleet-x4');
        $user = $this->makeUser($t);
        $this->seedFleet($t);

        $response = $this->actingAs($user, 'tenant')
            ->get('/app/fleet-x4/fleet?sort=daily_rate;drop table vehicles&direction=desc')
            ->assertOk();

        $this->assertNull($response->viewData('page')['props']['filters']['sort']);
        $this->assertCount(4, $this->rows($response));
    }

    public function test_other_tenants_vehicles_never_appear_or_count(): void
    {
        $a = $this->makeTenant('fleet-x5a');
        $userA = $this->makeUser($a);
        $this->makeVehicle($a, 'MINE1', today()->addDays(5)->toDateString(), null);

        $b = $this->makeTenant('fleet-x5b');
        $this->makeVehicle($b, 'THEIRS1', today()->addDays(5)->toDateString(), null);

        $response = $this->actingAs($userA, 'tenant')->get('/app/fleet-x5a/fleet?expiring=1')->assertOk();

        $this->assertSame(['MINE1'], collect($this->rows($response))->pluck('registration_number')->all());
        $this->assertSame(1, $response->viewData('page')['props']['expiringCount']);
    }
}
