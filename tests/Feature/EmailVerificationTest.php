<?php

namespace Tests\Feature;

use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\TenantEmailVerificationNotifier;
use App\Modules\SaasCore\Actions\AcceptStaffInvitationAction;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * SOFT email verification for the tenant guard: an unverified admin keeps
 * full access from the moment they sign up (CLAUDE.md decision) — this only
 * covers the trail itself (the signed link, the resend action, the dashboard
 * flag) never an access gate.
 */
class EmailVerificationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeAdmin(Tenant $tenant, ?string $verifiedAt = 'now'): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Jordan Blake', 'email' => 'jordan-'.uniqid().'@test.au',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
            'email_verified_at' => $verifiedAt === 'now' ? now() : $verifiedAt,
        ]);
    }

    public function test_registration_sends_a_verification_email(): void
    {
        $this->seed(TenantRolesSeeder::class);
        Plan::create([
            'name' => 'Free', 'slug' => 'free-'.Str::random(6), 'price_monthly' => 0, 'price_annual' => 0,
            'is_active' => true, 'is_free' => true, 'trial_days' => 14, 'modules' => [], 'limits' => [], 'sort_order' => 1,
        ]);

        $this->post('/register', [
            'admin_name' => 'Jordan Blake',
            'company_name' => 'Verify Co '.Str::random(6),
            'email' => 'owner@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertRedirect();

        $this->assertTrue(NotificationLog::where('event_type', 'email_verification')->exists());

        $user = TenantUser::where('email', 'owner@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at, 'soft verification: still unverified right after signup');
    }

    public function test_a_valid_signed_link_verifies_the_account_and_redirects_to_the_dashboard(): void
    {
        $tenant = $this->makeTenant('verify-link');
        $admin = $this->makeAdmin($tenant, verifiedAt: null);

        $url = app(TenantEmailVerificationNotifier::class)->buildUrl($tenant, $admin);

        $this->actingAs($admin, 'tenant')->get($url)
            ->assertRedirect(route('tenant.dashboard', ['tenant_slug' => $tenant->slug]));

        $this->assertNotNull($admin->fresh()->email_verified_at);
    }

    public function test_an_unsigned_or_tampered_link_is_rejected_and_never_verifies(): void
    {
        $tenant = $this->makeTenant('verify-tamper');
        $admin = $this->makeAdmin($tenant, verifiedAt: null);

        $this->get(route('tenant.verification.verify', [
            'tenant_slug' => $tenant->slug, 'id' => $admin->id, 'hash' => sha1($admin->email),
        ]))->assertForbidden(); // no signature at all

        $this->assertNull($admin->fresh()->email_verified_at);
    }

    public function test_a_link_whose_hash_no_longer_matches_the_email_fails_quietly(): void
    {
        $tenant = $this->makeTenant('verify-wronghash');
        $admin = $this->makeAdmin($tenant, verifiedAt: null);

        $url = URL::temporarySignedRoute('tenant.verification.verify', now()->addHour(), [
            'tenant_slug' => $tenant->slug, 'id' => $admin->id, 'hash' => sha1('someone-else@test.au'),
        ]);

        $this->get($url)
            ->assertRedirect(route('tenant.login', ['tenant_slug' => $tenant->slug]))
            ->assertSessionHasErrors('email');

        $this->assertNull($admin->fresh()->email_verified_at);
    }

    public function test_resend_sends_a_new_email_and_is_authenticated_only(): void
    {
        $tenant = $this->makeTenant('verify-resend');
        $admin = $this->makeAdmin($tenant, verifiedAt: null);

        $this->post("/app/{$tenant->slug}/email/resend")->assertRedirect(); // guest → login, not an error

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$tenant->slug}/email/resend")
            ->assertRedirect();

        $this->assertSame(
            1,
            NotificationLog::where('event_type', 'email_verification')
                ->where('notifiable_id', $admin->id)->count(),
        );
    }

    public function test_resend_is_a_no_op_once_already_verified(): void
    {
        $tenant = $this->makeTenant('verify-already');
        $admin = $this->makeAdmin($tenant); // verified by default

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$tenant->slug}/email/resend")
            ->assertSessionHas('success');

        $this->assertSame(0, NotificationLog::where('event_type', 'email_verification')->count());
    }

    public function test_the_dashboard_reports_the_current_verification_state(): void
    {
        $tenant = $this->makeTenant('verify-dashboard');
        $admin = $this->makeAdmin($tenant, verifiedAt: null);

        $this->actingAs($admin, 'tenant')->get("/app/{$tenant->slug}/dashboard")
            ->assertInertia(fn ($page) => $page->where('auth.user.email_verified', false));

        $admin->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($admin, 'tenant')->get("/app/{$tenant->slug}/dashboard")
            ->assertInertia(fn ($page) => $page->where('auth.user.email_verified', true));
    }

    public function test_accepting_a_staff_invitation_counts_as_verified(): void
    {
        $this->seed(TenantRolesSeeder::class);
        $tenant = $this->makeTenant('verify-staff');

        $invitation = TenantUserInvitation::create([
            'tenant_id' => $tenant->id,
            'name' => 'New Staffer',
            'email' => 'staffer@test.au',
            'role' => TenantUser::ROLE_STAFF,
            'token' => bin2hex(random_bytes(16)),
            'expires_at' => now()->addDays(7),
        ]);

        $user = app(AcceptStaffInvitationAction::class)->execute($invitation, 'password123');

        $this->assertNotNull($user->email_verified_at, 'clicking the unique invite link already proves ownership');
    }
}
