<?php

namespace Tests\Feature;

use App\Exceptions\PlanLimitExceededException;
use App\Modules\Customer\Actions\UploadCustomerDocumentAction;
use App\Modules\Customer\Events\CustomerDocumentUploaded;
use App\Modules\Customer\Models\Customer;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Customer identity documents (licence front/back, proof of address).
 * SENSITIVE: default (private) disk only, auth-checked download → signed URL,
 * tenant-isolated, plan file-size limit is a hard block. Real HTTP stack.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class CustomerDocumentTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $limits = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug,
            'slug' => 'plan-'.$slug.'-'.uniqid(),
            'price_monthly' => 5000,
            'price_annual' => 50000,
            'is_active' => true,
            'is_free' => false,
            'trial_days' => 14,
            'modules' => Plan::MODULE_KEYS,
            'limits' => $limits,
            'sort_order' => 0,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
        ]);

        app()->instance('current_tenant', $tenant);

        Subscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $email): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin',
            'email' => $email,
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    private function makeCustomer(Tenant $tenant): Customer
    {
        app()->instance('current_tenant', $tenant);

        return Customer::create([
            'name' => 'Renter',
            'email' => 'renter-'.uniqid().'@test.au',
            'phone' => '0400000000',
            'licence_number' => 'LIC-'.uniqid(),
            'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '0411111111',
        ]);
    }

    private function docUrl(Tenant $tenant, Customer $customer, string $type): string
    {
        return "/app/{$tenant->slug}/customers/{$customer->id}/documents/{$type}";
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    public function test_upload_stores_on_default_disk_under_tenant_customer_path(): void
    {
        $disk = Storage::fake(config('filesystems.default'));
        Event::fake([CustomerDocumentUploaded::class]);

        $tenant = $this->makeTenant('docs-a');
        $user = $this->makeUser($tenant, 'a@docs.test');
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($user, 'tenant')
            ->post($this->docUrl($tenant, $customer, 'license_front'), [
                'file' => UploadedFile::fake()->image('licence.jpg', 800, 500),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $path = $customer->fresh()->license_front_path;

        $this->assertNotNull($path);
        $this->assertMatchesRegularExpression(
            "#^tenants/{$tenant->id}/customers/{$customer->id}/license_front-[0-9a-f-]{36}\.jpg$#",
            $path,
        );
        $disk->assertExists($path);
        // Never on the public disk.
        $this->assertFalse(Storage::disk('public')->exists($path));

        Event::assertDispatched(CustomerDocumentUploaded::class, fn ($e) => $e->type === 'license_front' && ! $e->replaced);
    }

    public function test_replacing_deletes_the_old_file(): void
    {
        $disk = Storage::fake(config('filesystems.default'));
        Event::fake([CustomerDocumentUploaded::class]);

        $tenant = $this->makeTenant('docs-b');
        $user = $this->makeUser($tenant, 'b@docs.test');
        $customer = $this->makeCustomer($tenant);
        $url = $this->docUrl($tenant, $customer, 'proof_of_address');

        $this->actingAs($user, 'tenant')->post($url, ['file' => UploadedFile::fake()->create('bill.pdf', 200, 'application/pdf')]);
        $old = $customer->fresh()->proof_of_address_path;
        $disk->assertExists($old);

        $this->actingAs($user, 'tenant')->post($url, ['file' => UploadedFile::fake()->image('bill.png')])
            ->assertSessionHasNoErrors();
        $new = $customer->fresh()->proof_of_address_path;

        $this->assertNotSame($old, $new);
        $disk->assertMissing($old);
        $disk->assertExists($new);
        $this->assertCount(1, $disk->allFiles("tenants/{$tenant->id}/customers/{$customer->id}"));

        Event::assertDispatched(CustomerDocumentUploaded::class, fn ($e) => $e->replaced);
    }

    public function test_download_redirects_to_temporary_url_for_own_tenant(): void
    {
        Storage::fake(config('filesystems.default'));

        $tenant = $this->makeTenant('docs-c');
        $user = $this->makeUser($tenant, 'c@docs.test');
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($user, 'tenant')->post($this->docUrl($tenant, $customer, 'license_back'), [
            'file' => UploadedFile::fake()->image('back.jpg'),
        ]);
        $path = $customer->fresh()->license_back_path;

        $response = $this->actingAs($user, 'tenant')->get($this->docUrl($tenant, $customer, 'license_back'));

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString($path, $location);
        $this->assertStringContainsString('expiration=', $location);
    }

    public function test_download_missing_document_is_404(): void
    {
        $tenant = $this->makeTenant('docs-d');
        $user = $this->makeUser($tenant, 'd@docs.test');
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($user, 'tenant')
            ->get($this->docUrl($tenant, $customer, 'license_front'))
            ->assertNotFound();
    }

    public function test_guest_cannot_upload_or_download(): void
    {
        $tenant = $this->makeTenant('docs-e');
        $customer = $this->makeCustomer($tenant);

        $this->get($this->docUrl($tenant, $customer, 'license_front'))->assertRedirect();
        $this->post($this->docUrl($tenant, $customer, 'license_front'), [
            'file' => UploadedFile::fake()->image('x.jpg'),
        ])->assertRedirect();

        $this->assertNull($customer->fresh()->license_front_path);
    }

    public function test_other_tenant_cannot_download_or_upload(): void
    {
        $disk = Storage::fake(config('filesystems.default'));

        $tenantA = $this->makeTenant('docs-f1');
        $userA = $this->makeUser($tenantA, 'f1@docs.test');
        $customer = $this->makeCustomer($tenantA);

        $this->actingAs($userA, 'tenant')->post($this->docUrl($tenantA, $customer, 'license_front'), [
            'file' => UploadedFile::fake()->image('front.jpg'),
        ]);
        $original = $customer->fresh()->license_front_path;

        $tenantB = $this->makeTenant('docs-f2');
        $userB = $this->makeUser($tenantB, 'f2@docs.test');
        $this->forgetGuards();

        // Tenant B's own workspace, tenant A's customer id → TenantScope binding 404.
        $this->actingAs($userB, 'tenant')
            ->get($this->docUrl($tenantB, $customer, 'license_front'))
            ->assertNotFound();
        $this->actingAs($userB, 'tenant')
            ->post($this->docUrl($tenantB, $customer, 'license_front'), ['file' => UploadedFile::fake()->image('evil.jpg')])
            ->assertNotFound();

        // Tenant B user pointed at tenant A's workspace slug: the customer binds
        // (current_tenant = A) but CustomerPolicy::sameTenant refuses → 403.
        $this->actingAs($userB, 'tenant')
            ->get($this->docUrl($tenantA, $customer, 'license_front'))
            ->assertForbidden();
        $this->actingAs($userB, 'tenant')
            ->post($this->docUrl($tenantA, $customer, 'license_front'), ['file' => UploadedFile::fake()->image('evil.jpg')])
            ->assertForbidden();

        app()->instance('current_tenant', $tenantA);
        $this->assertSame($original, $customer->fresh()->license_front_path);
        $this->assertCount(1, $disk->allFiles());
    }

    public function test_file_over_10mb_is_rejected(): void
    {
        $disk = Storage::fake(config('filesystems.default'));

        $tenant = $this->makeTenant('docs-g');
        $user = $this->makeUser($tenant, 'g@docs.test');
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($user, 'tenant')
            ->post($this->docUrl($tenant, $customer, 'license_front'), [
                'file' => UploadedFile::fake()->create('huge.pdf', 10241, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertNull($customer->fresh()->license_front_path);
        $this->assertCount(0, $disk->allFiles());
    }

    public function test_plan_file_size_limit_is_enforced(): void
    {
        $disk = Storage::fake(config('filesystems.default'));

        $tenant = $this->makeTenant('docs-h', ['max_file_size_mb' => 1]);
        $user = $this->makeUser($tenant, 'h@docs.test');
        $customer = $this->makeCustomer($tenant);

        // HTTP: surfaces as an inline field error.
        $this->actingAs($user, 'tenant')
            ->post($this->docUrl($tenant, $customer, 'license_front'), [
                'file' => UploadedFile::fake()->create('scan.pdf', 2048, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');

        // Action (any caller): hard block.
        app()->instance('current_tenant', $tenant);
        try {
            app(UploadCustomerDocumentAction::class)->execute(
                $customer,
                'license_front',
                UploadedFile::fake()->create('scan.pdf', 2048, 'application/pdf'),
            );
            $this->fail('Expected PlanLimitExceededException');
        } catch (PlanLimitExceededException $e) {
            $this->assertSame('max_file_size_mb', $e->limitKey);
        }

        $this->assertNull($customer->fresh()->license_front_path);
        $this->assertCount(0, $disk->allFiles());
    }

    public function test_invalid_type_and_mime_are_rejected(): void
    {
        Storage::fake(config('filesystems.default'));

        $tenant = $this->makeTenant('docs-i');
        $user = $this->makeUser($tenant, 'i@docs.test');
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($user, 'tenant')
            ->post($this->docUrl($tenant, $customer, 'passport'), ['file' => UploadedFile::fake()->image('p.jpg')])
            ->assertNotFound();

        $this->actingAs($user, 'tenant')
            ->post($this->docUrl($tenant, $customer, 'license_front'), [
                'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_paths_are_hidden_from_serialization_and_show_page_gets_booleans(): void
    {
        Storage::fake(config('filesystems.default'));

        $tenant = $this->makeTenant('docs-j');
        $user = $this->makeUser($tenant, 'j@docs.test');
        $customer = $this->makeCustomer($tenant);

        $this->actingAs($user, 'tenant')->post($this->docUrl($tenant, $customer, 'license_front'), [
            'file' => UploadedFile::fake()->image('front.jpg'),
        ]);

        $fresh = $customer->fresh();
        $this->assertArrayNotHasKey('license_front_path', $fresh->toArray());

        $this->actingAs($user, 'tenant')
            ->get("/app/{$tenant->slug}/customers/{$customer->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('documents.license_front', true)
                ->where('documents.license_back', false)
                ->missing('customer.license_front_path'));
    }

    /**
     * End-to-end on the REAL default disk (no fake): the signed URL from the
     * download action serves the file; the same path unsigned is refused.
     */
    public function test_real_signed_url_serves_file_and_unsigned_is_refused(): void
    {
        if (config('filesystems.default') !== 'local') {
            $this->markTestSkipped('Signed local serving only applies to the local driver.');
        }

        $tenant = $this->makeTenant('docs-k-'.Str::lower(Str::random(6)));
        $user = $this->makeUser($tenant, 'k@docs.test');
        $customer = $this->makeCustomer($tenant);
        $dir = "tenants/{$tenant->id}";

        try {
            $this->actingAs($user, 'tenant')->post($this->docUrl($tenant, $customer, 'license_front'), [
                'file' => UploadedFile::fake()->image('front.png'),
            ])->assertSessionHasNoErrors();

            $path = $customer->fresh()->license_front_path;
            $this->assertFileExists(storage_path('app/private/'.$path));

            $signed = $this->actingAs($user, 'tenant')
                ->get($this->docUrl($tenant, $customer, 'license_front'))
                ->headers->get('Location');
            $this->assertStringContainsString('signature=', $signed);

            $this->forgetGuards();
            $this->get($signed)->assertOk();

            $unsigned = strtok($signed, '?');
            $this->get($unsigned)->assertForbidden();
        } finally {
            Storage::disk('local')->deleteDirectory($dir);
        }
    }
}
