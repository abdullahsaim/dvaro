<?php

namespace Tests\Feature;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Client feedback: the customer page said "rental history will appear here
 * once the rental module is ready" — permanently, since nothing ever filled
 * it in. There is no separate "Rental" module in this codebase; a rental IS
 * an Agreement, so this wires the placeholder up to real agreement data.
 *
 * Also covers client feedback #3: the end date on a new agreement must be
 * genuinely optional ("sometimes we don't know the end date yet") — already
 * nullable server-side; this proves it end-to-end through the real HTTP path.
 */
class CustomerRentalHistoryTest extends TestCase
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

    private function makeCustomer(Tenant $tenant): Customer
    {
        app()->instance('current_tenant', $tenant);

        return Customer::create([
            'name' => 'Jordan Blake', 'email' => 'jordan-'.uniqid().'@test.au', 'phone' => '0400',
            'licence_number' => 'WA1234567', 'emergency_contact_name' => 'Kin', 'emergency_contact_phone' => '1',
        ]);
    }

    private function makeVehicle(Tenant $tenant, string $rego): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        return Vehicle::create([
            'registration_number' => $rego, 'make' => 'Toyota', 'model' => 'Camry', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 8000,
        ]);
    }

    private function makeAgreement(Tenant $tenant, Customer $customer, array $attrs = []): Agreement
    {
        app()->instance('current_tenant', $tenant);

        return Agreement::create(array_merge([
            'customer_id' => $customer->id,
            'vehicle_id' => $this->makeVehicle($tenant, 'REG'.random_int(1000, 9999))->id,
            'type' => 'private',
            'status' => Agreement::STATUS_ACTIVE,
            'billing_cycle' => 'weekly',
            'rate' => 35000,
            'bond_amount' => 50000,
            'start_date' => today()->subWeek(),
            'version' => 1,
        ], $attrs));
    }

    // ── Rental history (issue 2) ─────────────────────────────────────────────

    public function test_a_customer_with_no_rentals_sees_an_empty_list(): void
    {
        $t = $this->makeTenant('rh-empty');
        $admin = $this->makeUser($t);
        $customer = $this->makeCustomer($t);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('rentalHistory', []));
    }

    public function test_rental_history_lists_one_row_per_rental(): void
    {
        $t = $this->makeTenant('rh-list');
        $admin = $this->makeUser($t);
        $customer = $this->makeCustomer($t);

        $older = $this->makeAgreement($t, $customer, ['start_date' => today()->subMonth(), 'status' => Agreement::STATUS_COMPLETED]);
        $recent = $this->makeAgreement($t, $customer, ['start_date' => today(), 'status' => Agreement::STATUS_ACTIVE]);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/customers/{$customer->id}")
            ->assertInertia(fn ($page) => $page
                ->has('rentalHistory', 2)
                ->where('rentalHistory.0.id', $recent->id) // most recent first
                ->where('rentalHistory.1.id', $older->id));
    }

    public function test_only_the_latest_version_of_an_amended_rental_is_shown(): void
    {
        $t = $this->makeTenant('rh-version');
        $admin = $this->makeUser($t);
        $customer = $this->makeCustomer($t);

        $v1 = $this->makeAgreement($t, $customer, ['status' => Agreement::STATUS_COMPLETED, 'version' => 1]);
        $v2 = $this->makeAgreement($t, $customer, [
            'status' => Agreement::STATUS_ACTIVE, 'version' => 2, 'parent_agreement_id' => $v1->id,
        ]);

        $response = $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/customers/{$customer->id}");

        $response->assertInertia(fn ($page) => $page
            ->has('rentalHistory', 1, fn ($row) => $row
                ->where('id', $v2->id)
                ->where('version', 2)
                ->etc()));
    }

    public function test_rental_history_is_scoped_to_this_customer_and_tenant(): void
    {
        $t = $this->makeTenant('rh-scope');
        $admin = $this->makeUser($t);
        $mine = $this->makeCustomer($t);
        $someoneElse = $this->makeCustomer($t);
        $this->makeAgreement($t, $someoneElse);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/customers/{$mine->id}")
            ->assertInertia(fn ($page) => $page->where('rentalHistory', []));
    }

    public function test_an_open_ended_rental_shows_no_end_date_rather_than_null(): void
    {
        $t = $this->makeTenant('rh-open');
        $admin = $this->makeUser($t);
        $customer = $this->makeCustomer($t);
        $this->makeAgreement($t, $customer, ['end_date' => null]);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/customers/{$customer->id}")
            ->assertInertia(fn ($page) => $page->where('rentalHistory.0.end_date', null));
    }

    // ── Flexible / optional end date on creation (issue 3) ───────────────────

    public function test_a_new_agreement_can_be_created_with_no_end_date(): void
    {
        $t = $this->makeTenant('rh-create-open');
        $admin = $this->makeUser($t);
        $customer = $this->makeCustomer($t);
        $vehicle = $this->makeVehicle($t, 'OPEN001');

        $this->actingAs($admin, 'tenant')->post("/app/{$t->slug}/agreements", [
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'private',
            'billing_cycle' => 'weekly',
            'rate' => 35000,
            'bond_amount' => 50000,
            'start_date' => today()->toDateString(),
            'end_date' => '', // "we don't always know the end date yet"
            'state' => 'WA',
        ])->assertSessionHasNoErrors();

        $agreement = Agreement::query()->where('customer_id', $customer->id)->latest('id')->first();
        $this->assertNull($agreement->end_date);
    }
}
