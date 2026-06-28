<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Security-critical: the mechanic portal is the fourth guard and must stay
 * isolated per tenant and separate from the tenant guard. Exercises the real
 * HTTP stack (ResolveTenantForMechanic + auth:mechanic + the guest-redirect
 * branch + the public QR scan flow).
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class WorkshopTest extends TestCase
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

    private function makeMechanic(Tenant $tenant, string $email, array $attrs = []): Mechanic
    {
        app()->instance('current_tenant', $tenant);

        return Mechanic::create(array_merge([
            'name' => "Mechanic of {$tenant->slug}",
            'email' => $email,
            'pin' => '1234',
            'password' => 'secret123',
            'is_active' => true,
        ], $attrs));
    }

    private function makeVehicle(Tenant $tenant, string $token): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        return Vehicle::create([
            'registration_number' => 'REG-'.$token,
            'make' => 'Toyota',
            'model' => 'Hilux',
            'year' => 2022,
            'status' => Vehicle::STATUS_AVAILABLE,
            'daily_rate' => 9000,
            'qr_code_token' => $token,
        ]);
    }

    public function test_mechanic_can_log_in_with_password(): void
    {
        $t = $this->makeTenant('shop-a');
        $this->makeMechanic($t, 'sam@shop.test');

        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'secret123',
        ])->assertRedirect('/mechanic/shop-a/dashboard');

        $this->assertAuthenticated('mechanic');
    }

    public function test_mechanic_can_log_in_with_pin(): void
    {
        $t = $this->makeTenant('shop-a');
        $this->makeMechanic($t, 'sam@shop.test');

        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'pin' => '1234',
        ])->assertRedirect('/mechanic/shop-a/dashboard');

        $this->assertAuthenticated('mechanic');
    }

    public function test_inactive_mechanic_cannot_log_in(): void
    {
        $t = $this->makeTenant('shop-a');
        $this->makeMechanic($t, 'sam@shop.test', ['is_active' => false]);

        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'secret123',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('mechanic');
    }

    public function test_mechanic_credentials_are_isolated_per_tenant(): void
    {
        $a = $this->makeTenant('shop-a');
        $b = $this->makeTenant('shop-b');
        $this->makeMechanic($a, 'sam@shop.test', ['password' => 'passA']);
        $this->makeMechanic($b, 'sam@shop.test', ['password' => 'passB']);

        // Tenant A's password must not authenticate against tenant B.
        $this->post('/mechanic/shop-b/login', [
            'email' => 'sam@shop.test',
            'password' => 'passA',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('mechanic');
    }

    public function test_guest_mechanic_is_redirected_to_the_mechanic_login(): void
    {
        $this->makeTenant('shop-a');

        // Validates redirectGuestsTo's mechanic branch + the priority-list pin
        // (ResolveTenantForMechanic must bind the tenant before auth:mechanic).
        $this->get('/mechanic/shop-a/dashboard')
            ->assertRedirect('/mechanic/shop-a/login');
    }

    public function test_public_scan_redirects_a_guest_to_login_and_stashes_the_token(): void
    {
        $t = $this->makeTenant('shop-a');
        $this->makeVehicle($t, 'tok-123');

        $this->get('/mechanic/shop-a/scan/tok-123')
            ->assertRedirect('/mechanic/shop-a/login')
            ->assertSessionHas('mechanic.intended_vehicle_token', 'tok-123');
    }

    public function test_public_scan_with_unknown_token_404s(): void
    {
        $this->makeTenant('shop-a');

        $this->get('/mechanic/shop-a/scan/does-not-exist')->assertNotFound();
    }

    public function test_public_scan_sends_an_authenticated_mechanic_to_the_vehicle(): void
    {
        $t = $this->makeTenant('shop-a');
        $this->makeMechanic($t, 'sam@shop.test');
        $this->makeVehicle($t, 'tok-123');

        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'secret123',
        ]);

        $this->forgetGuards();
        $this->get('/mechanic/shop-a/scan/tok-123')
            ->assertRedirect('/mechanic/shop-a/vehicle/tok-123');
    }

    public function test_mechanic_creating_a_log_puts_the_vehicle_into_maintenance(): void
    {
        $t = $this->makeTenant('shop-a');
        $this->makeMechanic($t, 'sam@shop.test');
        $vehicle = $this->makeVehicle($t, 'tok-123');

        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'secret123',
        ]);

        $this->forgetGuards();
        $this->post('/mechanic/shop-a/logs', [
            'token' => 'tok-123',
            'title' => 'Brake service',
            'labour_cost' => 3000,
        ])->assertRedirect('/mechanic/shop-a/vehicle/tok-123');

        app()->instance('current_tenant', $t);
        $this->assertSame(Vehicle::STATUS_MAINTENANCE, $vehicle->fresh()->status);
        $this->assertSame(1, ServiceLog::where('vehicle_id', $vehicle->id)->count());
    }

    public function test_tenant_session_cannot_access_the_mechanic_portal(): void
    {
        // A mechanic logged into tenant A must not be authenticated at tenant B.
        $a = $this->makeTenant('shop-a');
        $this->makeTenant('shop-b');
        $this->makeMechanic($a, 'sam@shop.test');

        $this->post('/mechanic/shop-a/login', [
            'email' => 'sam@shop.test',
            'password' => 'secret123',
        ])->assertRedirect('/mechanic/shop-a/dashboard');

        $this->forgetGuards();
        $this->get('/mechanic/shop-b/dashboard')
            ->assertRedirect('/mechanic/shop-b/login');
    }
}
