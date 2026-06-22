<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Security-critical: tenant-portal login must be isolated per tenant.
 *
 * Uses DatabaseTransactions (rolls back) — NOT RefreshDatabase, which would
 * migrate:fresh and wipe the shared dev database.
 */
class TenantAuthTest extends TestCase
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

    /**
     * Clear the in-memory resolved-guard cache so the next request re-resolves
     * the user from the session store — as a real, separate HTTP request would.
     * Without this, the AuthManager caches the user across in-process requests
     * and masks tenant-scoped re-resolution.
     */
    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function makeUser(Tenant $tenant, string $email, string $password): TenantUser
    {
        // Bind so HasTenant fills tenant_id and TenantScope is satisfied.
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => "User of {$tenant->slug}",
            'email' => $email,
            'password' => $password,
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    public function test_same_email_can_exist_in_two_tenants_as_distinct_users(): void
    {
        $a = $this->makeTenant('tenant-a');
        $b = $this->makeTenant('tenant-b');

        $ua = $this->makeUser($a, 'test@example.com', 'passwordA');
        $ub = $this->makeUser($b, 'test@example.com', 'passwordB');

        $this->assertNotSame($ua->id, $ub->id);
        $this->assertNotSame($ua->tenant_id, $ub->tenant_id);
    }

    public function test_user_can_log_into_their_own_tenant(): void
    {
        $a = $this->makeTenant('tenant-a');
        $this->makeUser($a, 'test@example.com', 'passwordA');

        $response = $this->post('/app/tenant-a/login', [
            'email' => 'test@example.com',
            'password' => 'passwordA',
        ]);

        $response->assertRedirect('/app/tenant-a/dashboard');
        $this->assertAuthenticated('tenant');
    }

    public function test_credentials_of_one_tenant_cannot_log_into_another_tenant(): void
    {
        $a = $this->makeTenant('tenant-a');
        $b = $this->makeTenant('tenant-b');

        // Same email exists in BOTH tenants but with different passwords.
        $this->makeUser($a, 'test@example.com', 'passwordA');
        $this->makeUser($b, 'test@example.com', 'passwordB');

        // Tenant A's password must NOT authenticate against tenant B.
        $response = $this->post('/app/tenant-b/login', [
            'email' => 'test@example.com',
            'password' => 'passwordA',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('tenant');
    }

    public function test_session_persists_across_requests(): void
    {
        $a = $this->makeTenant('tenant-a');
        $this->makeUser($a, 'test@example.com', 'passwordA');

        $this->post('/app/tenant-a/login', [
            'email' => 'test@example.com',
            'password' => 'passwordA',
        ])->assertRedirect('/app/tenant-a/dashboard');

        // Two protected hits in a row — each re-resolves from the session store
        // (guards forgotten) and is still authenticated.
        $this->forgetGuards();
        $this->get('/app/tenant-a/dashboard')->assertOk();

        $this->forgetGuards();
        $this->get('/app/tenant-a/dashboard')->assertOk();
    }

    public function test_tenant_a_session_does_not_authenticate_on_tenant_b(): void
    {
        $a = $this->makeTenant('tenant-a');
        $this->makeTenant('tenant-b');
        $this->makeUser($a, 'test@example.com', 'passwordA');

        $this->post('/app/tenant-a/login', [
            'email' => 'test@example.com',
            'password' => 'passwordA',
        ])->assertRedirect('/app/tenant-a/dashboard');

        // Same browser session, but tenant B's protected page must reject it:
        // re-resolution runs under tenant B's scope and finds no matching user.
        $this->forgetGuards();
        $this->get('/app/tenant-b/dashboard')
            ->assertRedirect('/app/tenant-b/login');
    }

    public function test_logout_ends_the_session(): void
    {
        $a = $this->makeTenant('tenant-a');
        $this->makeUser($a, 'test@example.com', 'passwordA');

        $this->post('/app/tenant-a/login', [
            'email' => 'test@example.com',
            'password' => 'passwordA',
        ]);

        $this->forgetGuards();
        $this->post('/app/tenant-a/logout')
            ->assertRedirect('/app/tenant-a/login');

        // After logout the dashboard bounces back to login.
        $this->forgetGuards();
        $this->get('/app/tenant-a/dashboard')
            ->assertRedirect('/app/tenant-a/login');
    }

    public function test_guest_cannot_reach_the_dashboard(): void
    {
        $this->makeTenant('tenant-a');

        $this->get('/app/tenant-a/dashboard')
            ->assertRedirect('/app/tenant-a/login');
    }

    public function test_login_is_rate_limited_after_five_failed_attempts(): void
    {
        $a = $this->makeTenant('tenant-a');
        $this->makeUser($a, 'test@example.com', 'passwordA');

        // Five wrong-password attempts are merely rejected.
        for ($i = 0; $i < 5; $i++) {
            $this->post('/app/tenant-a/login', [
                'email' => 'test@example.com',
                'password' => 'wrong',
            ])->assertSessionHasErrors('email');
        }

        // The sixth is throttled.
        $this->post('/app/tenant-a/login', [
            'email' => 'test@example.com',
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Too many',
            session('errors')->first('email'),
        );
    }
}
