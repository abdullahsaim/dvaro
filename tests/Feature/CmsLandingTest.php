<?php

namespace Tests\Feature;

use App\Modules\CMS\Events\DemoRequestSubmitted;
use App\Modules\CMS\Models\DemoRequest;
use App\Modules\CMS\Services\CmsContentService;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use Database\Seeders\CmsContentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as AssertInertia;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Public landing website + super admin CMS.
 *
 * Exercises the real HTTP stack: public pages render CMS content, pricing shows
 * only ACTIVE plans, the demo/contact forms capture leads and are rate-limited,
 * and the CMS editor is content-gated with caches busting on every write.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class CmsLandingTest extends TestCase
{
    use DatabaseTransactions;

    private function makeSuperAdmin(string $role = SuperAdmin::ROLE_PLATFORM_OWNER): SuperAdmin
    {
        Role::firstOrCreate(['name' => $role, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create([
            'name' => 'Admin',
            'email' => 'admin-'.uniqid().'@dvaro.test',
            'password' => 'secret1234',
            'role' => $role,
            'is_active' => true,
        ]);

        $admin->assignRole($role);

        return $admin;
    }

    private function makePlan(string $name, bool $active, int $sort = 0): Plan
    {
        return Plan::create([
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name).'-'.uniqid(),
            'description' => 'Plan '.$name,
            'price_monthly' => 9900,
            'price_annual' => 99000,
            'is_active' => $active,
            'is_free' => false,
            'trial_days' => 14,
            'modules' => ['fleet', 'invoice'],
            'limits' => ['max_vehicles' => 10],
            'sort_order' => $sort,
        ]);
    }

    public function test_landing_page_renders_with_cms_content(): void
    {
        $this->seed(CmsContentSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Public/Landing')
                ->where('hero.hero_heading', 'Run your rental fleet, not your spreadsheets.')
                ->has('features')
                ->has('plans'));
    }

    public function test_pricing_page_shows_only_active_plans(): void
    {
        // The dev DB may already hold plans from earlier sessions; neutralise
        // them inside this (rolled-back) transaction so the active-filter
        // assertion is deterministic.
        Plan::query()->update(['is_active' => false]);

        $active = $this->makePlan('Growth', true, 1);
        $this->makePlan('Legacy', false, 2);

        $this->get('/pricing')
            ->assertOk()
            ->assertInertia(fn (AssertInertia $page) => $page
                ->component('Public/Pricing')
                ->has('plans', 1)
                ->where('plans.0.name', $active->name));
    }

    public function test_demo_request_is_captured_and_fires_event(): void
    {
        Event::fake([DemoRequestSubmitted::class]);

        $this->post('/demo-request', [
            'company_name' => 'Acme Rentals',
            'contact_name' => 'Jane Doe',
            'email' => 'jane@acme.test',
            'phone' => '0400000000',
            'message' => 'Keen to see a demo.',
        ])->assertRedirect();

        $this->assertDatabaseHas('demo_requests', [
            'company_name' => 'Acme Rentals',
            'email' => 'jane@acme.test',
            'status' => DemoRequest::STATUS_NEW,
        ]);
        Event::assertDispatched(DemoRequestSubmitted::class);
    }

    public function test_demo_request_is_rate_limited_per_ip(): void
    {
        $payload = [
            'company_name' => 'Spam Co',
            'contact_name' => 'Bot',
            'email' => 'bot@spam.test',
        ];

        // 3 per hour are allowed; the 4th is throttled.
        $this->post('/demo-request', $payload)->assertRedirect();
        $this->post('/demo-request', $payload)->assertRedirect();
        $this->post('/demo-request', $payload)->assertRedirect();
        $this->post('/demo-request', $payload)->assertStatus(429);
    }

    public function test_contact_form_is_stored_as_a_demo_request(): void
    {
        $this->post('/contact', [
            'name' => 'Sam Buyer',
            'email' => 'sam@buyer.test',
            'message' => 'Do you support PayPal?',
        ])->assertRedirect();

        // Name maps to both company_name and contact_name; enquiry in message.
        $this->assertDatabaseHas('demo_requests', [
            'contact_name' => 'Sam Buyer',
            'company_name' => 'Sam Buyer',
            'email' => 'sam@buyer.test',
            'message' => 'Do you support PayPal?',
        ]);
    }

    public function test_super_admin_can_update_cms_text_and_cache_is_busted(): void
    {
        $this->seed(CmsContentSeeder::class);
        $admin = $this->makeSuperAdmin(SuperAdmin::ROLE_CONTENT_MANAGER);
        $cms = app(CmsContentService::class);

        // Warm the cache with the seeded value first.
        $this->assertSame('Run your rental fleet, not your spreadsheets.', $cms->get('hero_heading'));

        $this->actingAs($admin, 'superadmin')
            ->put('/superadmin/cms/hero_heading', ['content' => 'Brand new heading'])
            ->assertRedirect();

        // Cache was busted on write — the next read returns the new value.
        $this->assertSame('Brand new heading', $cms->get('hero_heading'));
        $this->assertDatabaseHas('cms_content_blocks', [
            'key' => 'hero_heading',
            'content' => 'Brand new heading',
        ]);
    }

    public function test_super_admin_can_upload_a_cms_image(): void
    {
        Storage::fake('public');
        $this->seed(CmsContentSeeder::class);
        $admin = $this->makeSuperAdmin(SuperAdmin::ROLE_CONTENT_MANAGER);

        $this->actingAs($admin, 'superadmin')
            ->post('/superadmin/cms/hero_image/image', [
                'image' => UploadedFile::fake()->image('hero.png', 800, 600),
            ])
            ->assertRedirect();

        $path = 'cms/images/hero_image.png';
        Storage::disk('public')->assertExists($path);
        $this->assertDatabaseHas('cms_content_blocks', [
            'key' => 'hero_image',
            'image_path' => $path,
        ]);
    }

    public function test_cms_editor_is_content_gated(): void
    {
        $this->seed(CmsContentSeeder::class);

        // billing_manager has no content access → 403.
        $billing = $this->makeSuperAdmin(SuperAdmin::ROLE_BILLING_MANAGER);
        $this->actingAs($billing, 'superadmin')->get('/superadmin/cms')->assertForbidden();

        // content_manager is allowed.
        $content = $this->makeSuperAdmin(SuperAdmin::ROLE_CONTENT_MANAGER);
        $this->actingAs($content, 'superadmin')->get('/superadmin/cms')->assertOk();
    }
}
