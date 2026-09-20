<?php

namespace Tests\Feature;

use App\Exceptions\AppendOnlyException;
use App\Jobs\SendStaffInvitationJob;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Services\LateFeeService;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\FleetReminderService;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Models\TenantUserInvitation;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Carbon\Carbon;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Settings & customization: the audit trail, the settings hub, company
 * profile, STAFF management (a rental company could not add a user at all
 * before this), late-fee rules that were previously unsettable, and the
 * regional timezone that now drives display AND when reminders are sent.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class SettingsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $settings = [], array $limits = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug, 'slug' => 'plan-'.$slug.'-'.uniqid(),
            'price_monthly' => 5000, 'price_annual' => 50000, 'is_active' => true, 'is_free' => false,
            'trial_days' => 14, 'modules' => Plan::MODULE_KEYS, 'limits' => $limits, 'sort_order' => 0,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug).' Rentals', 'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE, 'plan_id' => $plan->id,
            'settings' => $settings ?: null,
        ]);

        app()->instance('current_tenant', $tenant);

        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $role = TenantUser::ROLE_ADMIN, array $attrs = []): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create(array_merge([
            'name' => ucfirst(str_replace('tenant_', '', $role)),
            'email' => $role.'-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'role' => $role,
            'is_active' => true,
        ], $attrs));
    }

    private function url(Tenant $tenant, string $suffix = ''): string
    {
        return "/app/{$tenant->slug}/settings{$suffix}";
    }

    private function audits(Tenant $tenant, ?string $action = null)
    {
        app()->instance('current_tenant', $tenant);

        return AuditLog::query()
            ->when($action, fn ($q) => $q->where('action', $action))
            ->orderBy('id')
            ->get();
    }

    // ── Audit trail ─────────────────────────────────────────────────────────

    public function test_settings_changes_are_audited_with_old_and_new_values(): void
    {
        $t = $this->makeTenant('set-a');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/regional'), [
            'timezone' => 'Australia/Perth',
            'currency' => 'AUD',
            'date_format' => 'd M Y',
        ])->assertSessionHasNoErrors();

        $entry = $this->audits($t, 'settings.regional.updated')->sole();

        $this->assertSame('Australia/Sydney', $entry->old_values['timezone']);
        $this->assertSame('Australia/Perth', $entry->new_values['timezone']);
        // Unchanged keys are not recorded as noise.
        $this->assertArrayNotHasKey('currency', $entry->new_values);
        $this->assertSame($admin->name, $entry->actor_label);
        $this->assertSame(AuditLog::SUBJECT_SETTINGS, $entry->subject_type);
        $this->assertNotNull($entry->ip);
    }

    public function test_audit_entries_can_never_be_changed_or_deleted(): void
    {
        $t = $this->makeTenant('set-b');
        app()->instance('current_tenant', $t);

        $entry = AuditLog::query()->create([
            'tenant_id' => $t->id, 'action' => 'test', 'subject_type' => 'settings', 'created_at' => now(),
        ]);

        try {
            $entry->update(['action' => 'tampered']);
            $this->fail('Expected AppendOnlyException on update');
        } catch (AppendOnlyException) {
            // expected
        }

        $this->expectException(AppendOnlyException::class);
        $entry->delete();
    }

    public function test_activity_log_is_admin_only(): void
    {
        $t = $this->makeTenant('set-c');
        $admin = $this->makeUser($t);
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($admin, 'tenant')->get($this->url($t, '/activity'))->assertOk();
        $this->actingAs($staff, 'tenant')->get($this->url($t, '/activity'))->assertForbidden();
    }

    // ── Hub + company profile ───────────────────────────────────────────────

    public function test_hub_shows_the_right_cards_per_role(): void
    {
        $t = $this->makeTenant('set-d');
        $admin = $this->makeUser($t);
        $accounts = $this->makeUser($t, TenantUser::ROLE_ACCOUNTS);
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($admin, 'tenant')->get($this->url($t))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Index')
                ->where('isAdmin', true)
                ->where('isFinance', true)
                ->where('summary.staff_count', 3));

        $this->actingAs($accounts, 'tenant')->get($this->url($t))
            ->assertInertia(fn ($page) => $page->where('isAdmin', false)->where('isFinance', true));

        $this->actingAs($staff, 'tenant')->get($this->url($t))
            ->assertInertia(fn ($page) => $page->where('isAdmin', false)->where('isFinance', false)->has('recentActivity', 0));
    }

    public function test_company_profile_saves_and_validates(): void
    {
        $t = $this->makeTenant('set-e');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/company'), [
            'name' => 'Coastline Car Hire',
            'legal_name' => 'Coastline Pty Ltd',
            'abn' => '12 345 678 901',
            'phone' => '08 9000 0000',
            'email' => 'hello@coastline.test',
            'website' => 'https://coastline.test',
            'address' => '1 Beach Rd, Perth WA',
            'brand_colour' => '#1a2b3c',
            'default_state' => 'WA',
        ])->assertSessionHasNoErrors();

        $fresh = $t->fresh();
        $settings = app(TenantSettingsService::class)->all($fresh);

        $this->assertSame('Coastline Car Hire', $fresh->name);
        $this->assertSame('12345678901', $settings['abn']); // spaces stripped
        $this->assertSame('#1a2b3c', $settings['brand_colour']);
        $this->assertSame('WA', $settings['default_state']);

        // A rename is audited separately from the other details.
        $this->assertCount(1, $this->audits($t, 'settings.company_profile.renamed'));

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/company'), [
            'name' => 'X', 'abn' => '123', 'brand_colour' => 'red',
        ])->assertSessionHasErrors(['abn', 'brand_colour']);
    }

    public function test_logo_upload_is_public_and_replaces_cleanly(): void
    {
        $disk = Storage::fake('public');
        $t = $this->makeTenant('set-f');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')
            ->post($this->url($t, '/company/logo'), ['logo' => UploadedFile::fake()->image('logo.png', 400, 120)])
            ->assertSessionHasNoErrors();

        $first = app(TenantSettingsService::class)->get($t->fresh(), 'logo_path');
        $this->assertStringStartsWith("tenants/{$t->id}/branding/logo-", $first);
        $disk->assertExists($first);

        $this->actingAs($admin, 'tenant')
            ->post($this->url($t, '/company/logo'), ['logo' => UploadedFile::fake()->image('new.png')]);
        $second = app(TenantSettingsService::class)->get($t->fresh(), 'logo_path');

        $disk->assertMissing($first);
        $disk->assertExists($second);

        $this->actingAs($admin, 'tenant')->delete($this->url($t, '/company/logo'))->assertSessionHasNoErrors();
        $this->assertNull(app(TenantSettingsService::class)->get($t->fresh(), 'logo_path'));
        $this->assertCount(0, $disk->allFiles());
    }

    public function test_only_admin_writes_company_profile_and_regional(): void
    {
        $t = $this->makeTenant('set-g');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')->get($this->url($t, '/company'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($staff, 'tenant')->put($this->url($t, '/company'), ['name' => 'Hijack'])->assertForbidden();
        $this->actingAs($staff, 'tenant')->put($this->url($t, '/regional'), [
            'timezone' => 'Australia/Perth', 'currency' => 'AUD', 'date_format' => 'd/m/Y',
        ])->assertForbidden();

        $this->assertSame('Set-g Rentals', $t->fresh()->name);
    }

    // ── Staff ───────────────────────────────────────────────────────────────

    public function test_inviting_staff_queues_an_email_and_never_sets_a_password(): void
    {
        Queue::fake();
        $t = $this->makeTenant('set-h');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'Riley Stone', 'email' => 'Riley@Example.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $invite = TenantUserInvitation::query()->sole();

        $this->assertSame('riley@example.test', $invite->email); // normalised
        $this->assertTrue($invite->isPending());
        $this->assertSame((int) $admin->id, (int) $invite->invited_by);
        // No account exists until they accept.
        $this->assertSame(1, TenantUser::query()->count());

        Queue::assertPushedOn('notifications', SendStaffInvitationJob::class);
        $this->assertCount(1, $this->audits($t, 'staff.invited'));
    }

    public function test_invitation_email_carries_a_working_link(): void
    {
        $t = $this->makeTenant('set-i');
        $admin = $this->makeUser($t);
        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'Sam', 'email' => 'sam@example.test', 'role' => TenantUser::ROLE_STAFF,
        ]);

        // The queue runs synchronously in tests, so the job has already sent.
        app()->instance('current_tenant', $t);
        $invite = TenantUserInvitation::query()->sole();

        $log = NotificationLog::where('event_type', SendStaffInvitationJob::EVENT_TYPE)
            ->where('recipient', 'sam@example.test')
            ->sole();
        $this->assertSame('sam@example.test', $log->recipient);
        $this->assertStringContainsString($invite->token, $log->body);
    }

    public function test_accepting_an_invitation_creates_the_account_and_signs_in(): void
    {
        $this->seed(TenantRolesSeeder::class);
        $t = $this->makeTenant('set-j');
        $admin = $this->makeUser($t);
        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'Pat Lane', 'email' => 'pat@example.test', 'role' => TenantUser::ROLE_ACCOUNTS,
        ]);

        app()->instance('current_tenant', $t);
        $invite = TenantUserInvitation::query()->sole();

        $this->app['auth']->forgetGuards();
        $this->get("/app/{$t->slug}/invitation/{$invite->token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Auth/AcceptStaffInvitation')->where('valid', true));

        $this->post("/app/{$t->slug}/invitation/{$invite->token}", [
            'password' => 'brand-new-pass', 'password_confirmation' => 'brand-new-pass',
        ])->assertRedirect("/app/{$t->slug}/dashboard");

        app()->instance('current_tenant', $t);
        $user = TenantUser::query()->where('email', 'pat@example.test')->sole();
        $this->assertSame(TenantUser::ROLE_ACCOUNTS, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertAuthenticatedAs($user, 'tenant');

        // The link is single-use.
        $this->assertNotNull($invite->fresh()->accepted_at);
        $this->post("/app/{$t->slug}/invitation/{$invite->token}", [
            'password' => 'another-pass-1', 'password_confirmation' => 'another-pass-1',
        ])->assertSessionHasErrors();
        $this->assertSame(1, TenantUser::query()->where('email', 'pat@example.test')->count());
    }

    public function test_expired_and_revoked_invitations_cannot_be_used(): void
    {
        $t = $this->makeTenant('set-k');
        $admin = $this->makeUser($t);
        app()->instance('current_tenant', $t);

        $expired = TenantUserInvitation::query()->create([
            'name' => 'Old', 'email' => 'old@example.test', 'role' => TenantUser::ROLE_STAFF,
            'token' => (string) Str::uuid(), 'expires_at' => now()->subDay(),
        ]);
        $pending = TenantUserInvitation::query()->create([
            'name' => 'New', 'email' => 'new@example.test', 'role' => TenantUser::ROLE_STAFF,
            'token' => (string) Str::uuid(), 'expires_at' => now()->addDays(7),
        ]);

        $this->app['auth']->forgetGuards();
        $this->get("/app/{$t->slug}/invitation/{$expired->token}")
            ->assertInertia(fn ($page) => $page->where('valid', false)->where('status', 'expired'));

        $this->actingAs($admin, 'tenant')
            ->delete($this->url($t, "/staff/invitations/{$pending->id}"))
            ->assertSessionHasNoErrors();

        $this->app['auth']->forgetGuards();
        $this->get("/app/{$t->slug}/invitation/{$pending->token}")
            ->assertInertia(fn ($page) => $page->where('valid', false)->where('status', 'revoked'));

        app()->instance('current_tenant', $t);
        $this->assertSame(1, TenantUser::query()->count()); // only the admin
    }

    public function test_duplicate_invitations_and_existing_emails_are_rejected(): void
    {
        Queue::fake();
        $t = $this->makeTenant('set-l');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN, ['email' => 'boss@set-l.test']);

        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'Dup', 'email' => 'BOSS@set-l.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertSessionHasErrors('email');

        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'First', 'email' => 'new@set-l.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'Second', 'email' => 'new@set-l.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertSessionHasErrors('email');
    }

    public function test_staff_seats_are_capped_by_the_plan_including_pending_invites(): void
    {
        Queue::fake();
        $t = $this->makeTenant('set-m', [], ['max_staff_users' => 2]);
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'One', 'email' => 'one@set-m.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertSessionHasNoErrors();

        // 1 user + 1 pending invitation = the 2-seat plan is full.
        $this->actingAs($admin, 'tenant')->post($this->url($t, '/staff/invite'), [
            'name' => 'Two', 'email' => 'two@set-m.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertSessionHasErrors('plan_limit');

        app()->instance('current_tenant', $t);
        $this->assertSame(1, TenantUserInvitation::query()->count());
    }

    public function test_role_changes_and_deactivation_with_guardrails(): void
    {
        $this->seed(TenantRolesSeeder::class);
        $t = $this->makeTenant('set-n');
        $admin = $this->makeUser($t);
        $other = $this->makeUser($t, TenantUser::ROLE_STAFF);

        // Promote someone else — fine.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$other->id}"), ['role' => TenantUser::ROLE_ACCOUNTS])
            ->assertSessionHasNoErrors();
        $this->assertSame(TenantUser::ROLE_ACCOUNTS, $other->fresh()->role);

        // Your own role / your own account are off limits.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$admin->id}"), ['role' => TenantUser::ROLE_STAFF])
            ->assertSessionHasErrors('role');
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$admin->id}"), ['is_active' => false])
            ->assertSessionHasErrors('is_active');

        // Deactivate + reactivate someone else.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$other->id}"), ['is_active' => false])
            ->assertSessionHasNoErrors();
        $this->assertFalse($other->fresh()->is_active);

        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$other->id}"), ['is_active' => true])
            ->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->is_active);

        $this->assertGreaterThanOrEqual(3, $this->audits($t, 'staff.updated')->count());
    }

    public function test_the_last_admin_cannot_be_demoted_or_switched_off(): void
    {
        $this->seed(TenantRolesSeeder::class);
        $t = $this->makeTenant('set-o');
        $admin = $this->makeUser($t);
        $second = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        // Two admins: demoting one is allowed.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$second->id}"), ['role' => TenantUser::ROLE_STAFF])
            ->assertSessionHasNoErrors();

        // Now only one admin remains — and they are the actor, so both the
        // self-guard and the last-admin guard protect the workspace.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/staff/{$admin->id}"), ['role' => TenantUser::ROLE_STAFF])
            ->assertSessionHasErrors();

        $this->assertSame(TenantUser::ROLE_ADMIN, $admin->fresh()->role);
        $this->assertTrue($admin->fresh()->is_active);
    }

    public function test_deactivated_staff_cannot_sign_in_and_logins_are_stamped(): void
    {
        $t = $this->makeTenant('set-p');
        $active = $this->makeUser($t, TenantUser::ROLE_STAFF, ['email' => 'active@set-p.test']);
        $this->makeUser($t, TenantUser::ROLE_STAFF, ['email' => 'off@set-p.test', 'is_active' => false]);

        $this->post("/app/{$t->slug}/login", ['email' => 'off@set-p.test', 'password' => 'secret123'])
            ->assertSessionHasErrors('email');
        $this->assertGuest('tenant');

        $this->app['auth']->forgetGuards();
        $this->post("/app/{$t->slug}/login", ['email' => 'active@set-p.test', 'password' => 'secret123'])
            ->assertRedirect("/app/{$t->slug}/dashboard");

        $this->assertNotNull($active->fresh()->last_login_at);
    }

    public function test_staff_list_is_read_only_for_non_admins_and_tenant_isolated(): void
    {
        $a = $this->makeTenant('set-q1');
        $adminA = $this->makeUser($a);
        $staffA = $this->makeUser($a, TenantUser::ROLE_STAFF);

        $b = $this->makeTenant('set-q2');
        $adminB = $this->makeUser($b);

        $this->actingAs($staffA, 'tenant')->get($this->url($a, '/staff'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false)->has('staff', 2));
        $this->actingAs($staffA, 'tenant')->post($this->url($a, '/staff/invite'), [
            'name' => 'X', 'email' => 'x@set-q1.test', 'role' => TenantUser::ROLE_STAFF,
        ])->assertForbidden();

        // Another tenant's staff id is simply not found.
        $this->actingAs($adminB, 'tenant')->put($this->url($b, "/staff/{$staffA->id}"), ['role' => TenantUser::ROLE_ADMIN])
            ->assertNotFound();
        $this->assertSame(TenantUser::ROLE_STAFF, $staffA->fresh()->role);

        $this->actingAs($adminB, 'tenant')->get($this->url($b, '/staff'))
            ->assertInertia(fn ($page) => $page->has('staff', 1));
    }

    // ── Late fees (previously unsettable) ───────────────────────────────────

    public function test_late_fee_settings_change_what_is_charged(): void
    {
        $t = $this->makeTenant('set-r');
        $accounts = $this->makeUser($t, TenantUser::ROLE_ACCOUNTS);

        $this->actingAs($accounts, 'tenant')->put($this->url($t, '/finance'), [
            'late_fees_enabled' => 1,
            'late_fee_grace_days' => 10,
            'late_fee_type' => 'percentage',
            'late_fee_percentage' => 5,
            'invoice_payment_terms_days' => 14,
            'invoice_prefix' => 'CC-',
        ])->assertSessionHasNoErrors();

        $settings = app(TenantSettingsService::class)->all($t->fresh());
        $this->assertSame(10, $settings['late_fee_grace_days']);
        $this->assertSame('percentage', $settings['late_fee_type']);

        // A 5% fee on a $1,000 invoice = $50, applied only past the grace period.
        $invoice = $this->makeOverdueInvoice($t, days: 11, total: 100000);
        app(LateFeeService::class)->applyDueLateFees();

        app()->instance('current_tenant', $t);
        $this->assertSame(105000, $invoice->fresh()->total);
    }

    public function test_late_fees_can_be_switched_off_entirely(): void
    {
        $t = $this->makeTenant('set-s', ['late_fees_enabled' => false]);
        $invoice = $this->makeOverdueInvoice($t, days: 30, total: 100000);

        app(LateFeeService::class)->applyDueLateFees();

        app()->instance('current_tenant', $t);
        $this->assertSame(100000, $invoice->fresh()->total);
    }

    public function test_finance_settings_need_admin_or_accounts(): void
    {
        $t = $this->makeTenant('set-t');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')->get($this->url($t, '/finance'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($staff, 'tenant')->put($this->url($t, '/finance'), [
            'late_fees_enabled' => 0, 'late_fee_grace_days' => 1, 'late_fee_type' => 'fixed',
            'late_fee_amount' => 100, 'invoice_payment_terms_days' => 7,
        ])->assertForbidden();
    }

    // ── Regional / timezone ─────────────────────────────────────────────────

    public function test_timezone_is_shared_to_the_front_end(): void
    {
        $t = $this->makeTenant('set-u', ['timezone' => 'Australia/Perth', 'date_format' => 'd M Y']);
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->get($this->url($t))
            ->assertInertia(fn ($page) => $page
                ->where('tenant.timezone', 'Australia/Perth')
                ->where('tenant.date_format', 'd M Y')
                ->where('tenant.currency', 'AUD'));
    }

    public function test_fleet_digest_is_sent_at_seven_in_each_tenants_own_timezone(): void
    {
        // July (no DST): 07:00 Perth is 09:00 Sydney, so only Perth is due.
        Carbon::setTestNow(Carbon::create(2026, 7, 1, 9, 5, 0, 'Australia/Sydney'));

        $perth = $this->makeTenant('set-v1', ['timezone' => 'Australia/Perth']);
        $this->makeUser($perth, TenantUser::ROLE_ADMIN, ['email' => 'admin@set-v1.test']);
        $this->makeVehicleDue($perth, 'PER111');

        $sydney = $this->makeTenant('set-v2', ['timezone' => 'Australia/Sydney']);
        $this->makeUser($sydney, TenantUser::ROLE_ADMIN, ['email' => 'admin@set-v2.test']);
        $this->makeVehicleDue($sydney, 'SYD222');

        app()->forgetInstance('current_tenant');
        app(FleetReminderService::class)->sweep(localHour: 7);

        $this->assertSame(1, $this->digestCount($perth), 'Perth tenant should receive its 7am digest');
        $this->assertSame(0, $this->digestCount($sydney), 'Sydney tenant is not at 7am yet');

        // Next morning it is 07:00 in Sydney.
        Carbon::setTestNow(Carbon::create(2026, 7, 2, 7, 5, 0, 'Australia/Sydney'));
        app()->forgetInstance('current_tenant');
        app(FleetReminderService::class)->sweep(localHour: 7);

        $this->assertSame(1, $this->digestCount($sydney));

        Carbon::setTestNow();
    }

    public function test_invalid_regional_values_are_rejected(): void
    {
        $t = $this->makeTenant('set-w');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/regional'), [
            'timezone' => 'Mars/Olympus', 'currency' => 'USD', 'date_format' => 'nonsense',
        ])->assertSessionHasErrors(['timezone', 'currency', 'date_format']);
    }

    // ── Integrations ────────────────────────────────────────────────────────

    public function test_integrations_can_be_set_and_report_configuration(): void
    {
        $t = $this->makeTenant('set-x');
        $admin = $this->makeUser($t);
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($admin, 'tenant')->get($this->url($t, '/integrations'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/Integrations')
                ->where('settings.ai_provider', 'log')
                ->has('configured.groq')
                ->has('platform.stripe'));

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/integrations'), [
            'ai_provider' => 'groq', 'email_provider' => 'log', 'sms_provider' => 'log',
        ])->assertSessionHasNoErrors();

        $this->assertSame('groq', app(TenantSettingsService::class)->get($t->fresh(), 'ai_provider'));
        $this->assertCount(1, $this->audits($t, 'settings.integrations.updated'));

        $this->actingAs($staff, 'tenant')->put($this->url($t, '/integrations'), [
            'ai_provider' => 'qwen', 'email_provider' => 'log', 'sms_provider' => 'log',
        ])->assertForbidden();
    }

    public function test_settings_service_fills_defaults_and_keeps_unrelated_keys(): void
    {
        $t = $this->makeTenant('set-y', ['lead_form_intro' => 'Hi there']);
        $service = app(TenantSettingsService::class);

        $this->assertSame('Australia/Sydney', $service->get($t, 'timezone'));
        $this->assertSame(3, $service->get($t, 'late_fee_grace_days'));

        $service->update($t, ['timezone' => 'Australia/Darwin'], 'regional');

        $all = $service->all($t->fresh());
        $this->assertSame('Australia/Darwin', $all['timezone']);
        $this->assertSame('Hi there', $all['lead_form_intro']); // untouched
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    private function makeOverdueInvoice(Tenant $tenant, int $days, int $total): Invoice
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Renter', 'email' => 'r-'.uniqid().'@test.au', 'phone' => '0400',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);

        return Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => today()->subDays($days + 5),
            'due_date' => today()->subDays($days),
            'billing_period_start' => today()->subDays($days + 35),
            'billing_period_end' => today()->subDays($days + 5),
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => 0,
        ]);
    }

    private function makeVehicleDue(Tenant $tenant, string $rego): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        $vehicle = new Vehicle([
            'registration_number' => $rego, 'make' => 'Toyota', 'model' => 'Hilux', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 9000,
            'registration_expiry' => today()->addDays(5)->toDateString(),
        ]);
        $vehicle->save();

        return $vehicle;
    }

    private function digestCount(Tenant $tenant): int
    {
        app()->instance('current_tenant', $tenant);

        return NotificationLog::where('event_type', FleetReminderService::EVENT_TYPE)->count();
    }
}
