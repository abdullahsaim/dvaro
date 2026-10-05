<?php

namespace Tests\Feature;

use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use App\Modules\SuperAdmin\Models\PlatformCredential;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\SuperAdmin\Services\PlatformCredentialService;
use App\Providers\PlatformCredentialOverrideServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Platform-wide credentials (Super Admin → Settings → Credentials): the
 * encrypted-at-rest store (PlatformCredentialService), its config() override
 * (PlatformCredentialOverrideServiceProvider), and the HTTP surface
 * (IntegrationCredentialsController) — platformOwner-only, blank fields never
 * clear an existing value, and no credential value is ever logged or echoed
 * back to the browser.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class PlatformCredentialsTest extends TestCase
{
    use DatabaseTransactions;

    private function makeSuperAdmin(string $role = SuperAdmin::ROLE_PLATFORM_OWNER): SuperAdmin
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create([
            'name' => 'Owner',
            'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret1234',
            'role' => $role,
            'is_active' => true,
        ]);

        $admin->assignRole($role);

        return $admin;
    }

    // ------------------------------------------------------------------
    // Service: set/get, encryption at rest, blank-never-clears
    // ------------------------------------------------------------------

    public function test_set_and_get_roundtrip(): void
    {
        $service = app(PlatformCredentialService::class);

        $service->set('stripe_secret', 'sk_live_abc123');

        $this->assertSame('sk_live_abc123', $service->get('stripe_secret'));
        $this->assertTrue($service->isSet('stripe_secret'));
    }

    public function test_value_is_encrypted_at_rest(): void
    {
        app(PlatformCredentialService::class)->set('mailgun_secret', 'key-super-secret-value');

        $row = PlatformCredential::query()->where('key', 'mailgun_secret')->first();

        $this->assertNotNull($row);
        $this->assertStringNotContainsString('key-super-secret-value', (string) $row->getRawOriginal('value'));
    }

    public function test_setting_null_clears_the_credential(): void
    {
        $service = app(PlatformCredentialService::class);
        $service->set('resend_key', 're_abc');
        $this->assertTrue($service->isSet('resend_key'));

        $service->set('resend_key', null);

        $this->assertFalse($service->isSet('resend_key'));
        $this->assertNull($service->get('resend_key'));
    }

    // ------------------------------------------------------------------
    // Override provider: a DB value overrides config()
    // ------------------------------------------------------------------

    public function test_override_provider_applies_db_value_onto_config(): void
    {
        app(PlatformCredentialService::class)->set('groq_key', 'gsk_test_value');

        app()->getProvider(PlatformCredentialOverrideServiceProvider::class)->applyOverrides();

        $this->assertSame('gsk_test_value', config('services.groq.key'));
    }

    public function test_override_provider_leaves_config_untouched_when_unset(): void
    {
        config(['services.qwen.key' => 'from-env-fallback']);

        app()->getProvider(PlatformCredentialOverrideServiceProvider::class)->applyOverrides();

        $this->assertSame('from-env-fallback', config('services.qwen.key'));
    }

    public function test_override_provider_int_casts_smtp_port(): void
    {
        app(PlatformCredentialService::class)->set('smtp_port', '2525');

        app()->getProvider(PlatformCredentialOverrideServiceProvider::class)->applyOverrides();

        $this->assertSame(2525, config('mail.mailers.smtp.port'));
    }

    // ------------------------------------------------------------------
    // HTTP surface
    // ------------------------------------------------------------------

    public function test_non_owner_cannot_view_credentials(): void
    {
        $support = $this->makeSuperAdmin(SuperAdmin::ROLE_SUPPORT_AGENT);

        $this->actingAs($support, 'superadmin')
            ->get('/superadmin/settings/credentials')
            ->assertForbidden();
    }

    public function test_owner_can_view_credentials_page_with_status_only(): void
    {
        $owner = $this->makeSuperAdmin();
        app(PlatformCredentialService::class)->set('stripe_secret', 'sk_live_xyz');

        $response = $this->actingAs($owner, 'superadmin')->get('/superadmin/settings/credentials');

        $response->assertOk();
        // The actual secret value must never appear anywhere in the response.
        $response->assertDontSee('sk_live_xyz');
    }

    public function test_owner_can_set_a_credential(): void
    {
        $owner = $this->makeSuperAdmin();

        $this->actingAs($owner, 'superadmin')
            ->put('/superadmin/settings/credentials', ['stripe_secret' => 'sk_live_new'])
            ->assertRedirect();

        $this->assertSame('sk_live_new', app(PlatformCredentialService::class)->get('stripe_secret'));
    }

    public function test_blank_field_does_not_clear_an_existing_credential(): void
    {
        $owner = $this->makeSuperAdmin();
        app(PlatformCredentialService::class)->set('resend_key', 're_existing');

        $this->actingAs($owner, 'superadmin')
            ->put('/superadmin/settings/credentials', ['resend_key' => ''])
            ->assertRedirect();

        $this->assertSame('re_existing', app(PlatformCredentialService::class)->get('resend_key'));
    }

    public function test_explicit_clear_removes_the_credential(): void
    {
        $owner = $this->makeSuperAdmin();
        app(PlatformCredentialService::class)->set('resend_key', 're_existing');

        $this->actingAs($owner, 'superadmin')
            ->put('/superadmin/settings/credentials', ['clear' => ['resend_key']])
            ->assertRedirect();

        $this->assertFalse(app(PlatformCredentialService::class)->isSet('resend_key'));
    }

    public function test_update_logs_only_key_names_never_values(): void
    {
        $owner = $this->makeSuperAdmin();

        $this->actingAs($owner, 'superadmin')
            ->put('/superadmin/settings/credentials', ['paypal_client_secret' => 'super-secret-value'])
            ->assertRedirect();

        $entry = PlatformActivityLog::query()->where('action', 'platform_credentials.updated')->latest('id')->first();

        $this->assertNotNull($entry);
        $this->assertStringNotContainsString('super-secret-value', json_encode($entry->new_values));
        $this->assertContains('paypal_client_secret', $entry->new_values['keys_changed'] ?? []);
    }

    public function test_non_owner_cannot_update_credentials(): void
    {
        $billing = $this->makeSuperAdmin(SuperAdmin::ROLE_BILLING_MANAGER);

        $this->actingAs($billing, 'superadmin')
            ->put('/superadmin/settings/credentials', ['stripe_secret' => 'sk_live_blocked'])
            ->assertForbidden();

        $this->assertFalse(app(PlatformCredentialService::class)->isSet('stripe_secret'));
    }
}
