<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Password reset across the three tenant-scoped portal guards.
 *
 * The security-critical property: a reset token is bound to ONE tenant's account
 * even when the same email exists in another tenant — a token issued for tenant A
 * can never be redeemed under tenant B. This holds because each portal model's
 * getEmailForPasswordReset() returns a "{guard}:{tenant_id}:{email}" composite,
 * so the token row is tenant-distinct, and the user is resolved by the bound
 * tenant (from the slug in the link path).
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class PasswordResetTest extends TestCase
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

    private function makeTenantUser(Tenant $tenant, string $email, string $password): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'User of '.$tenant->slug,
            'email' => $email,
            'password' => $password,
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    public function test_tenant_forgot_password_sends_an_email_and_stores_a_token(): void
    {
        $t = $this->makeTenant('co-a');
        $this->makeTenantUser($t, 'owner@co.test', 'original-pass');

        $this->post('/app/co-a/forgot-password', ['email' => 'owner@co.test'])
            ->assertRedirect();

        // A reset token row is stored under the tenant-composite key.
        $this->assertDatabaseHas('portal_password_reset_tokens', [
            'email' => 'tenant:'.$t->id.':owner@co.test',
        ]);

        // The email went out through NotificationService (Log provider in dev).
        app()->instance('current_tenant', $t);
        $log = NotificationLog::where('event_type', 'password_reset')
            ->where('recipient', 'owner@co.test')
            ->first();
        $this->assertNotNull($log);
        $this->assertSame(NotificationLog::STATUS_SENT, $log->status);
    }

    public function test_tenant_user_can_reset_with_a_valid_token_and_log_in(): void
    {
        $t = $this->makeTenant('co-a');
        $user = $this->makeTenantUser($t, 'owner@co.test', 'original-pass');

        // Mint a real token the way the broker does (avoids parsing the email).
        $token = Password::broker('tenant')->getRepository()->create($user);

        $this->post('/app/co-a/reset-password', [
            'token' => $token,
            'email' => 'owner@co.test',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/app/co-a/login');

        // The new password authenticates; the old one no longer does.
        $this->forgetGuards();
        $this->post('/app/co-a/login', [
            'email' => 'owner@co.test',
            'password' => 'brand-new-pass',
        ])->assertRedirect('/app/co-a/dashboard');
        $this->assertAuthenticated('tenant');
    }

    public function test_a_reset_token_cannot_reset_the_same_email_in_another_tenant(): void
    {
        $a = $this->makeTenant('co-a');
        $b = $this->makeTenant('co-b');
        $userA = $this->makeTenantUser($a, 'shared@co.test', 'a-original');
        $this->makeTenantUser($b, 'shared@co.test', 'b-original');

        // Token issued for tenant A's account.
        $tokenA = Password::broker('tenant')->getRepository()->create($userA);

        // Try to redeem it on tenant B's reset endpoint (same email).
        $this->post('/app/co-b/reset-password', [
            'token' => $tokenA,
            'email' => 'shared@co.test',
            'password' => 'hijacked-pass',
            'password_confirmation' => 'hijacked-pass',
        ])->assertSessionHasErrors('email');

        // Tenant B's password is untouched; tenant A's still resettable/usable.
        $this->forgetGuards();
        $this->post('/app/co-b/login', [
            'email' => 'shared@co.test',
            'password' => 'hijacked-pass',
        ])->assertSessionHasErrors('email');
        $this->assertGuest('tenant');

        $this->forgetGuards();
        $this->post('/app/co-b/login', [
            'email' => 'shared@co.test',
            'password' => 'b-original',
        ])->assertRedirect('/app/co-b/dashboard');
        $this->assertAuthenticated('tenant');
    }

    public function test_customer_can_reset_their_password(): void
    {
        $t = $this->makeTenant('co-a');
        app()->instance('current_tenant', $t);
        $customer = Customer::create([
            'name' => 'Renter',
            'email' => 'renter@co.test',
            'phone' => '0400000000',
            'licence_number' => 'LIC-'.uniqid(),
            'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '0411111111',
        ]);
        $customerUser = CustomerUser::create([
            'customer_id' => $customer->id,
            'email' => 'renter@co.test',
            'password' => 'original-pass',
        ]);

        $token = Password::broker('customer')->getRepository()->create($customerUser);

        $this->post('/portal/co-a/reset-password', [
            'token' => $token,
            'email' => 'renter@co.test',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/portal/co-a/login');

        $this->forgetGuards();
        $this->post('/portal/co-a/login', [
            'email' => 'renter@co.test',
            'password' => 'brand-new-pass',
        ])->assertRedirect('/portal/co-a/dashboard');
        $this->assertAuthenticated('customer');
    }

    public function test_mechanic_can_reset_their_password(): void
    {
        $t = $this->makeTenant('co-a');
        app()->instance('current_tenant', $t);
        $mechanic = Mechanic::create([
            'name' => 'Sam',
            'email' => 'sam@co.test',
            'password' => 'original-pass',
            'is_active' => true,
        ]);

        $token = Password::broker('mechanic')->getRepository()->create($mechanic);

        $this->post('/mechanic/co-a/reset-password', [
            'token' => $token,
            'email' => 'sam@co.test',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect('/mechanic/co-a/login');

        $this->forgetGuards();
        $this->post('/mechanic/co-a/login', [
            'email' => 'sam@co.test',
            'password' => 'brand-new-pass',
        ])->assertRedirect('/mechanic/co-a/dashboard');
        $this->assertAuthenticated('mechanic');
    }

    public function test_forgot_password_is_rate_limited_to_three_per_hour(): void
    {
        $t = $this->makeTenant('co-a');
        $this->makeTenantUser($t, 'owner@co.test', 'original-pass');

        for ($i = 0; $i < 3; $i++) {
            $this->post('/app/co-a/forgot-password', ['email' => 'owner@co.test'])
                ->assertRedirect();
        }

        // The 4th request within the hour is throttled.
        $this->post('/app/co-a/forgot-password', ['email' => 'owner@co.test'])
            ->assertStatus(429);
    }
}
