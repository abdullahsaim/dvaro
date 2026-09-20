<?php

namespace Tests\Feature;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\FleetReminderService;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Services\UserPreferences;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Personal settings: where each person lands after signing in, how many rows
 * their lists show, and their own opt-outs. Per PERSON, not per company —
 * nothing here may leak into a colleague's experience.
 *
 * The dark/light preference has its own endpoint and its own test
 * (UserPreferenceTest).
 */
class UserPreferencesTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug, 'slug' => 'plan-'.$slug.'-'.uniqid(),
            'price_monthly' => 5000, 'price_annual' => 50000, 'is_active' => true, 'is_free' => false,
            'trial_days' => 14, 'modules' => Plan::MODULE_KEYS, 'limits' => [], 'sort_order' => 0,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE, 'plan_id' => $plan->id,
        ]);

        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function staff(Tenant $tenant, string $email, array $attrs = []): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create(array_merge([
            'name' => 'Staffer', 'email' => $email, 'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ], $attrs));
    }

    // ── The service ─────────────────────────────────────────────────────────

    public function test_defaults_apply_until_someone_chooses(): void
    {
        $t = $this->makeTenant('pr-a');
        $user = $this->staff($t, 'a@pr-a.test');
        $prefs = app(UserPreferences::class);

        $this->assertSame('dashboard', $prefs->landingPage($user, 'tenant'));
        $this->assertSame(15, $prefs->rowsPerPage($user));
        $this->assertSame(30, $prefs->rowsPerPage($user, 30), 'a screen keeps its own default');
        $this->assertFalse($prefs->get($user, 'mute_fleet_digest'));
    }

    public function test_invalid_values_are_dropped_not_stored(): void
    {
        $prefs = app(UserPreferences::class);

        $clean = $prefs->sanitize([
            'landing_page' => 'payroll',      // not a page this guard has
            'rows_per_page' => 9999,          // not an offered size
            'mute_fleet_digest' => '1',
            'is_admin' => true,               // not a preference at all
        ], 'tenant');

        $this->assertSame(
            ['landing_page' => null, 'rows_per_page' => 15, 'mute_fleet_digest' => true],
            $clean,
        );

        // Customers have no fleet digest, so the opt-out is not theirs to set.
        $this->assertArrayNotHasKey('mute_fleet_digest', $prefs->sanitize(['mute_fleet_digest' => true], 'customer'));
    }

    public function test_a_stale_landing_page_never_breaks_signing_in(): void
    {
        $t = $this->makeTenant('pr-b');
        $user = $this->staff($t, 'b@pr-b.test', ['preferences' => ['landing_page' => 'gone']]);

        $this->assertSame('tenant.dashboard', app(UserPreferences::class)->landingRoute($user, 'tenant'));
    }

    // ── Saving ──────────────────────────────────────────────────────────────

    public function test_staff_can_save_their_own_preferences(): void
    {
        $t = $this->makeTenant('pr-c');
        $user = $this->staff($t, 'c@pr-c.test');

        $this->actingAs($user, 'tenant')->get("/app/{$t->slug}/profile")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile/TenantProfile')
                ->where('preferences.rows_per_page', 15)
                ->where('landingPages', UserPreferences::LANDING_PAGES['tenant'])
                ->where('rowsPerPageOptions', UserPreferences::ROWS_PER_PAGE));

        $this->actingAs($user, 'tenant')->put("/app/{$t->slug}/profile/preferences", [
            'preferences' => [
                'landing_page' => 'fleet',
                'rows_per_page' => 50,
                'mute_fleet_digest' => true,
            ],
        ])->assertSessionHasNoErrors();

        $saved = $user->fresh()->preferences;

        $this->assertSame('fleet', $saved['landing_page']);
        $this->assertSame(50, $saved['rows_per_page']);
        $this->assertTrue($saved['mute_fleet_digest']);
    }

    public function test_one_persons_preferences_do_not_touch_a_colleagues(): void
    {
        $t = $this->makeTenant('pr-d');
        $mine = $this->staff($t, 'mine@pr-d.test');
        $theirs = $this->staff($t, 'theirs@pr-d.test', ['role' => TenantUser::ROLE_STAFF]);

        $this->actingAs($mine, 'tenant')->put("/app/{$t->slug}/profile/preferences", [
            'preferences' => ['rows_per_page' => 100],
        ])->assertSessionHasNoErrors();

        $this->assertSame(100, app(UserPreferences::class)->rowsPerPage($mine->fresh()));
        $this->assertSame(15, app(UserPreferences::class)->rowsPerPage($theirs->fresh()));
    }

    // ── Effects ─────────────────────────────────────────────────────────────

    public function test_signing_in_lands_on_the_chosen_page(): void
    {
        $t = $this->makeTenant('pr-e');
        $this->staff($t, 'lands@pr-e.test', ['preferences' => ['landing_page' => 'invoices']]);

        $this->post("/app/{$t->slug}/login", ['email' => 'lands@pr-e.test', 'password' => 'secret123'])
            ->assertRedirect("/app/{$t->slug}/invoices");
    }

    public function test_rows_per_page_changes_what_a_list_returns(): void
    {
        $t = $this->makeTenant('pr-f');
        $user = $this->staff($t, 'lists@pr-f.test');

        app()->instance('current_tenant', $t);
        foreach (range(1, 18) as $i) {
            (new Vehicle([
                'registration_number' => 'PRF'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'make' => 'Toyota', 'model' => 'Corolla', 'year' => 2023,
                'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 8000,
            ]))->save();
        }

        $this->actingAs($user, 'tenant')->get("/app/{$t->slug}/fleet")
            ->assertInertia(fn ($page) => $page->has('vehicles.data', 15));

        $user->forceFill(['preferences' => ['rows_per_page' => 25]])->save();

        $this->actingAs($user, 'tenant')->get("/app/{$t->slug}/fleet")
            ->assertInertia(fn ($page) => $page->has('vehicles.data', 18));
    }

    public function test_muting_the_fleet_digest_only_silences_that_person(): void
    {
        $t = $this->makeTenant('pr-g');
        $this->staff($t, 'quiet@pr-g.test', ['preferences' => ['mute_fleet_digest' => true]]);
        $this->staff($t, 'loud@pr-g.test', ['role' => TenantUser::ROLE_STAFF]);

        app()->instance('current_tenant', $t);
        (new Vehicle([
            'registration_number' => 'PRG111', 'make' => 'Ford', 'model' => 'Ranger', 'year' => 2024,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 9000,
            'registration_expiry' => today()->addDays(5)->toDateString(),
        ]))->save();

        app()->forgetInstance('current_tenant');
        app(FleetReminderService::class)->sweep();

        app()->instance('current_tenant', $t);
        $recipients = NotificationLog::where('event_type', FleetReminderService::EVENT_TYPE)
            ->pluck('recipient');

        $this->assertSame(['loud@pr-g.test'], $recipients->all());
    }

    // ── Other guards ────────────────────────────────────────────────────────

    public function test_super_admins_have_their_own_preferences(): void
    {
        $admin = SuperAdmin::create([
            'name' => 'Platform Owner', 'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret123', 'role' => SuperAdmin::ROLE_PLATFORM_OWNER, 'is_active' => true,
        ]);

        $this->actingAs($admin, 'superadmin')->put('/superadmin/profile/preferences', [
            'preferences' => ['landing_page' => 'tenants', 'rows_per_page' => 50],
        ])->assertSessionHasNoErrors();

        $this->assertSame('tenants', $admin->fresh()->preferences['landing_page']);
        $this->assertSame('superadmin.tenants.index', app(UserPreferences::class)->landingRoute($admin->fresh(), 'superadmin'));
    }

    public function test_preferences_require_a_signed_in_user(): void
    {
        $t = $this->makeTenant('pr-h');

        $this->put("/app/{$t->slug}/profile/preferences", ['preferences' => ['rows_per_page' => 50]])
            ->assertRedirect("/app/{$t->slug}/login");
    }
}
