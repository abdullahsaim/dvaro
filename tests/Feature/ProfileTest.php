<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\Workshop\Models\Mechanic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Profile settings for all four authenticated guards (tenant, customer,
 * mechanic, superadmin): name/email updates, the tenant-scoped vs global email
 * uniqueness rules, Hash::check-verified password changes, the mechanic PIN
 * flow (including the PIN-only mechanic changing a password via their PIN),
 * and the customer portal's email-only update. Real HTTP stack.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class ProfileTest extends TestCase
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

    private function makeTenantUser(Tenant $tenant, string $email, string $password = 'secret123'): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Staff '.$email,
            'email' => $email,
            'password' => $password,
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    private function makeMechanic(Tenant $tenant, string $email, ?string $pin, ?string $password): Mechanic
    {
        app()->instance('current_tenant', $tenant);

        return Mechanic::create([
            'name' => 'Mech '.$email,
            'email' => $email,
            'pin' => $pin,
            'password' => $password,
            'is_active' => true,
        ]);
    }

    private function makeCustomerUser(Tenant $tenant, string $email, string $password = 'secret123'): CustomerUser
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Renter '.$email,
            'email' => $email,
            'phone' => '0400000000',
            'licence_number' => 'LIC-'.uniqid(),
            'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '0411111111',
        ]);

        return CustomerUser::create([
            'customer_id' => $customer->id,
            'email' => $email,
            'password' => $password,
        ]);
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    /** The stored bcrypt hash (bypasses $hidden serialisation, not access). */
    private function storedHash(object $user, string $column): ?string
    {
        return $user->refresh()->getAttributes()[$column] ?? null;
    }

    // ── Tenant guard ─────────────────────────────────────────────────────────

    public function test_tenant_user_updates_name_and_email(): void
    {
        $t = $this->makeTenant('prof-a');
        $user = $this->makeTenantUser($t, 'old@prof.test');

        $response = $this->actingAs($user, 'tenant')->put('/app/prof-a/profile', [
            'name' => 'New Name',
            'email' => 'new@prof.test',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('new@prof.test', $user->email);
    }

    public function test_tenant_email_must_be_unique_within_the_tenant_but_not_across_tenants(): void
    {
        $tenantA = $this->makeTenant('prof-uniq-a');
        $userA = $this->makeTenantUser($tenantA, 'me@uniq.test');
        $this->makeTenantUser($tenantA, 'taken@uniq.test');

        // Same tenant → rejected.
        $response = $this->actingAs($userA, 'tenant')->put('/app/prof-uniq-a/profile', [
            'name' => $userA->name,
            'email' => 'taken@uniq.test',
        ]);
        $response->assertSessionHasErrors('email');
        $this->assertSame('me@uniq.test', $userA->refresh()->email);

        // The same address already lives on ANOTHER tenant → allowed (email is
        // per-tenant, never global, matching registration/login).
        $tenantB = $this->makeTenant('prof-uniq-b');
        $this->makeTenantUser($tenantB, 'shared@uniq.test');

        $response = $this->actingAs($userA, 'tenant')->put('/app/prof-uniq-a/profile', [
            'name' => $userA->name,
            'email' => 'shared@uniq.test',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertSame('shared@uniq.test', $userA->refresh()->email);
    }

    public function test_tenant_user_can_resave_their_own_email(): void
    {
        $t = $this->makeTenant('prof-own');
        $user = $this->makeTenantUser($t, 'own@prof.test');

        // The unique rule must ignore the user's own row.
        $response = $this->actingAs($user, 'tenant')->put('/app/prof-own/profile', [
            'name' => 'Renamed Only',
            'email' => 'own@prof.test',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('Renamed Only', $user->refresh()->name);
    }

    public function test_password_change_requires_the_correct_current_password(): void
    {
        $t = $this->makeTenant('prof-pw');
        $user = $this->makeTenantUser($t, 'pw@prof.test', 'oldpass123');

        // Wrong current password → rejected, hash unchanged.
        $response = $this->actingAs($user, 'tenant')->put('/app/prof-pw/profile/password', [
            'current_password' => 'not-the-password',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);
        $response->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('oldpass123', $this->storedHash($user, 'password')));

        // Correct current password → updated.
        $response = $this->actingAs($user, 'tenant')->put('/app/prof-pw/profile/password', [
            'current_password' => 'oldpass123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('newpass123', $this->storedHash($user, 'password')));
    }

    public function test_new_password_works_on_next_login(): void
    {
        $t = $this->makeTenant('prof-relogin');
        $user = $this->makeTenantUser($t, 'relogin@prof.test', 'oldpass123');

        $this->actingAs($user, 'tenant')->put('/app/prof-relogin/profile/password', [
            'current_password' => 'oldpass123',
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertSessionHasNoErrors();

        $this->post('/app/prof-relogin/logout');
        $this->forgetGuards();

        $response = $this->post('/app/prof-relogin/login', [
            'email' => 'relogin@prof.test',
            'password' => 'newpass123',
        ]);

        $response->assertRedirect('/app/prof-relogin/dashboard');
        $this->assertAuthenticatedAs($user, 'tenant');
    }

    // ── Mechanic guard ───────────────────────────────────────────────────────

    public function test_mechanic_updates_pin_and_the_new_pin_logs_in(): void
    {
        $t = $this->makeTenant('prof-mech');
        $mechanic = $this->makeMechanic($t, 'mech@prof.test', '1234', 'mechpass123');

        $response = $this->actingAs($mechanic, 'mechanic')->put('/mechanic/prof-mech/profile/pin', [
            'current_password' => 'mechpass123',
            'pin' => '567890',
            'pin_confirmation' => '567890',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('567890', $this->storedHash($mechanic, 'pin')));

        // The new PIN authenticates on the mechanic PIN login path.
        $this->post('/mechanic/prof-mech/logout');
        $this->forgetGuards();

        $this->post('/mechanic/prof-mech/login', [
            'email' => 'mech@prof.test',
            'pin' => '567890',
        ]);
        $this->assertAuthenticatedAs($mechanic, 'mechanic');
    }

    public function test_pin_only_mechanic_verifies_with_pin_and_rejects_bad_pin(): void
    {
        $t = $this->makeTenant('prof-mech-pin');
        // No password at all — the PIN is the mechanic's only credential.
        $mechanic = $this->makeMechanic($t, 'pinonly@prof.test', '4321', null);

        // A wrong credential is rejected.
        $response = $this->actingAs($mechanic, 'mechanic')->put('/mechanic/prof-mech-pin/profile/password', [
            'current_password' => '9999',
            'password' => 'firstpass123',
            'password_confirmation' => 'firstpass123',
        ]);
        $response->assertSessionHasErrors('current_password');

        // The current PIN proves identity, letting a PIN-only mechanic add a
        // password (the client-approved either-credential decision).
        $response = $this->actingAs($mechanic, 'mechanic')->put('/mechanic/prof-mech-pin/profile/password', [
            'current_password' => '4321',
            'password' => 'firstpass123',
            'password_confirmation' => 'firstpass123',
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('firstpass123', $this->storedHash($mechanic, 'password')));
    }

    public function test_pin_must_be_4_to_6_digits(): void
    {
        $t = $this->makeTenant('prof-mech-digits');
        $mechanic = $this->makeMechanic($t, 'digits@prof.test', '1234', 'mechpass123');

        foreach (['12', '1234567', '12ab'] as $badPin) {
            $response = $this->actingAs($mechanic, 'mechanic')->put('/mechanic/prof-mech-digits/profile/pin', [
                'current_password' => 'mechpass123',
                'pin' => $badPin,
                'pin_confirmation' => $badPin,
            ]);
            $response->assertSessionHasErrors('pin');
        }

        $this->assertTrue(Hash::check('1234', $this->storedHash($mechanic, 'pin')));
    }

    // ── Customer guard ───────────────────────────────────────────────────────

    public function test_customer_updates_email_only_and_sees_the_readonly_name(): void
    {
        $t = $this->makeTenant('prof-cust');
        $user = $this->makeCustomerUser($t, 'renter@prof.test');

        $this->actingAs($user, 'customer')->get('/portal/prof-cust/profile')->assertOk();

        $response = $this->actingAs($user, 'customer')->put('/portal/prof-cust/profile', [
            'email' => 'renter-new@prof.test',
        ]);

        $response->assertRedirect()->assertSessionHas('success');
        $user->refresh();
        $this->assertSame('renter-new@prof.test', $user->email);
        // The linked Customer record is untouched — its name (and email) change
        // only via the tenant admin's customer management.
        $this->assertSame('renter@prof.test', $user->customer->email);
    }

    public function test_customer_email_unique_within_tenant(): void
    {
        $t = $this->makeTenant('prof-cust-uniq');
        $user = $this->makeCustomerUser($t, 'c1@prof.test');
        $this->makeCustomerUser($t, 'c2@prof.test');

        $response = $this->actingAs($user, 'customer')->put('/portal/prof-cust-uniq/profile', [
            'email' => 'c2@prof.test',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertSame('c1@prof.test', $user->refresh()->email);
    }

    // ── Super admin guard ────────────────────────────────────────────────────

    public function test_super_admin_updates_profile_and_email_is_globally_unique(): void
    {
        $admin = SuperAdmin::create([
            'name' => 'Owner',
            'email' => 'owner@prof.test',
            'password' => 'ownerpass123',
            'role' => SuperAdmin::ROLE_PLATFORM_OWNER,
            'is_active' => true,
        ]);
        SuperAdmin::create([
            'name' => 'Billing',
            'email' => 'billing@prof.test',
            'password' => 'billingpass1',
            'role' => SuperAdmin::ROLE_BILLING_MANAGER,
            'is_active' => true,
        ]);

        // Another super admin's email → rejected (global uniqueness, no tenant).
        $response = $this->actingAs($admin, 'superadmin')->put('/superadmin/profile', [
            'name' => 'Owner',
            'email' => 'billing@prof.test',
        ]);
        $response->assertSessionHasErrors('email');

        // A fresh address (own row ignored) → updated.
        $response = $this->actingAs($admin, 'superadmin')->put('/superadmin/profile', [
            'name' => 'Owner Renamed',
            'email' => 'owner-new@prof.test',
        ]);
        $response->assertSessionHasNoErrors();
        $admin->refresh();
        $this->assertSame('Owner Renamed', $admin->name);
        $this->assertSame('owner-new@prof.test', $admin->email);
    }

    // ── Authentication gate ──────────────────────────────────────────────────

    public function test_guests_are_redirected_to_each_guards_login(): void
    {
        $this->makeTenant('prof-guest');

        $this->get('/app/prof-guest/profile')
            ->assertRedirect('/app/prof-guest/login');
        $this->forgetGuards();

        $this->get('/portal/prof-guest/profile')
            ->assertRedirect('/portal/prof-guest/login');
        $this->forgetGuards();

        $this->get('/mechanic/prof-guest/profile')
            ->assertRedirect('/mechanic/prof-guest/login');
        $this->forgetGuards();

        $this->get('/superadmin/profile')
            ->assertRedirect('/superadmin/login');
    }
}
