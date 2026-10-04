<?php

namespace Tests\Feature;

use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Public tenant self-registration flow.
 *
 * Uses DatabaseTransactions (rolls back) — NOT RefreshDatabase, which would
 * migrate:fresh and wipe the shared dev database.
 */
class TenantRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // tenant_admin must exist under guard 'tenant' for assignRole().
        $this->seed(TenantRolesSeeder::class);
    }

    /**
     * A free/active plan onboarding can fall back to. Plans are platform-wide
     * (not tenant-scoped), so this is a plain create.
     */
    private function makePlan(): Plan
    {
        return Plan::create([
            'name' => 'Test Free',
            'slug' => 'test-free-'.Str::random(6),
            'price_monthly' => 0,
            'price_annual' => 0,
            'is_active' => true,
            'is_free' => true,
            'trial_days' => 14,
            'modules' => [],
            'limits' => [],
            'sort_order' => 1,
        ]);
    }

    private function uniqueCompany(): string
    {
        return 'Acme Rentals '.Str::random(6);
    }

    public function test_registration_creates_tenant_user_and_subscription_in_one_go(): void
    {
        $this->makePlan();
        $company = $this->uniqueCompany();
        $slug = Str::slug($company);

        $response = $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => $company,
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect("/app/{$slug}/dashboard");

        $tenant = Tenant::where('slug', $slug)->firstOrFail();
        $this->assertSame(Tenant::STATUS_TRIAL, $tenant->status);

        // Subscription + admin user exist, both scoped to the new tenant.
        app()->instance('current_tenant', $tenant);

        $this->assertTrue(
            Subscription::where('tenant_id', $tenant->id)
                ->where('status', Subscription::STATUS_TRIALING)
                ->exists(),
        );

        $user = TenantUser::where('email', 'owner@example.com')->firstOrFail();
        $this->assertSame(TenantUser::ROLE_ADMIN, $user->role);
        $this->assertTrue($user->hasRole(TenantUser::ROLE_ADMIN));
        // The admin's own name — distinct from the company name — is what the
        // dashboard greets them with, not the company's.
        $this->assertSame('Jordan Blake', $user->name);
        $this->assertSame($company, $tenant->name);
    }

    public function test_registration_logs_the_new_admin_in(): void
    {
        $this->makePlan();
        $company = $this->uniqueCompany();

        $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => $company,
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $this->assertAuthenticated('tenant');
    }

    public function test_failed_onboarding_rolls_everything_back(): void
    {
        // No active plan exists → TenantOnboardingService throws → full rollback.
        // (Guard against a stray seeded plan leaking in.)
        Plan::query()->update(['is_active' => false]);

        $company = $this->uniqueCompany();
        $slug = Str::slug($company);

        $response = $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => $company,
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest('tenant');
        $this->assertNull(Tenant::where('slug', $slug)->first());
    }

    public function test_password_must_be_confirmed(): void
    {
        $this->makePlan();

        $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => $this->uniqueCompany(),
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'mismatch',
        ])->assertSessionHasErrors('password');

        $this->assertGuest('tenant');
    }

    public function test_register_redirects_away_when_already_tenant_authenticated(): void
    {
        $this->makePlan();
        $company = $this->uniqueCompany();

        // Register (this logs the admin into the tenant guard for the session).
        $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => $company,
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // Returning to /register in the same session is bounced to root by the
        // guest.tenant middleware — and crucially does NOT throw
        // TenantNotResolvedException (no scoped lookup on this unbound route).
        $this->get('/register')->assertRedirect('/');
    }

    public function test_two_companies_with_the_same_name_get_distinct_slugs(): void
    {
        $this->makePlan();
        $company = $this->uniqueCompany();

        $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => $company,
            'email' => 'owner-one@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/app/'.Str::slug($company).'/dashboard');

        $firstTenant = Tenant::where('slug', Str::slug($company))->firstOrFail();

        // Log the first admin out — guest.tenant would otherwise bounce a
        // second /register attempt before it ever reaches the slug collision.
        $this->post("/app/{$firstTenant->slug}/logout");

        // Same company name again — must NOT crash on the unique slug
        // constraint; it gets a distinguishing suffix instead.
        $this->post('/register', [
            'admin_name' => 'Casey Nguyen',
            'company_name' => $company,
            'email' => 'owner-two@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect('/app/'.Str::slug($company).'-2/dashboard');

        $secondTenant = Tenant::where('slug', Str::slug($company).'-2')->firstOrFail();
        $this->assertNotSame($firstTenant->id, $secondTenant->id);
        $this->assertSame($company, $secondTenant->name, 'the NAME stays identical — only the slug disambiguates');
    }
}
