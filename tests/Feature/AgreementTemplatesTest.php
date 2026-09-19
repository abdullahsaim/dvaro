<?php

namespace Tests\Feature;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Models\AgreementTemplate;
use App\Modules\Agreement\Services\AgreementTemplateService;
use App\Modules\Agreement\Services\AgreementTermsSanitizer;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Client feedback #3 — agreement terms templates: per state + type, freely
 * editable, HTML sanitised on save, merge fields validated and escaped, the
 * selection cascade (own → platform defaults), and above all the FREEZE:
 * editing a template must never change an agreement that already exists.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class AgreementTemplatesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // The dev/staging DB carries the seeded SAMPLE platform defaults, which
        // would win the selection cascade over this test's fixtures. Park them
        // for the duration of each test — DatabaseTransactions rolls it back.
        AgreementTemplate::platformDefaults()->update(['is_active' => false]);
    }

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create(['name' => ucfirst($slug).' Rentals', 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $role = TenantUser::ROLE_ADMIN): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => ucfirst($role), 'email' => $role.'-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => $role,
        ]);
    }

    private function makeSuperAdmin(): SuperAdmin
    {
        Role::firstOrCreate(['name' => SuperAdmin::ROLE_PLATFORM_OWNER, 'guard_name' => 'superadmin']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $admin = SuperAdmin::create([
            'name' => 'Owner', 'email' => 'owner-'.uniqid().'@dvaro.test',
            'password' => 'secret1234', 'role' => SuperAdmin::ROLE_PLATFORM_OWNER, 'is_active' => true,
        ]);
        $admin->assignRole(SuperAdmin::ROLE_PLATFORM_OWNER);

        return $admin;
    }

    private function makeTemplate(?Tenant $tenant, array $attrs = []): AgreementTemplate
    {
        $template = AgreementTemplate::query()->create(array_merge([
            'tenant_id' => $tenant?->id,
            'name' => 'Terms '.uniqid(),
            'agreement_type' => null,
            'state' => null,
            'body_html' => '<p>Generic terms for {{customer.name}}.</p>',
            'revision' => 1,
            'is_active' => true,
        ], $attrs));

        // HasTenant fills tenant_id from the bound tenant when it is empty, so a
        // platform default (tenant_id NULL) has to be forced back.
        if ($tenant === null && $template->tenant_id !== null) {
            $template->forceFill(['tenant_id' => null])->save();
        }

        return $template;
    }

    private function makeCustomer(Tenant $tenant, string $name = 'Jordan Blake'): Customer
    {
        app()->instance('current_tenant', $tenant);

        return Customer::create([
            'name' => $name, 'email' => 'c-'.uniqid().'@test.au', 'phone' => '0400',
            'licence_number' => 'WA1234567', 'emergency_contact_name' => 'Kin', 'emergency_contact_phone' => '1',
            'address' => '12 Example St',
        ]);
    }

    private function makeVehicle(Tenant $tenant, string $rego): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        return Vehicle::create([
            'registration_number' => $rego, 'make' => 'Toyota', 'model' => 'Camry', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 8000,
        ]);
    }

    /** Create an agreement through the real HTTP path. */
    private function createAgreement(Tenant $tenant, TenantUser $user, array $overrides = []): Agreement
    {
        $customer = $overrides['customer'] ?? $this->makeCustomer($tenant);
        $vehicle = $overrides['vehicle'] ?? $this->makeVehicle($tenant, 'REG'.random_int(1000, 9999));

        $this->actingAs($user, 'tenant')->post("/app/{$tenant->slug}/agreements", array_merge([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'type' => Agreement::TYPE_PRIVATE,
            'billing_cycle' => 'weekly',
            'rate' => 35000,
            'bond_amount' => 50000,
            'start_date' => today()->toDateString(),
            'state' => 'WA',
        ], $overrides['payload'] ?? []))->assertSessionHasNoErrors();

        app()->instance('current_tenant', $tenant);

        return Agreement::query()->latest('id')->first();
    }

    private function url(Tenant $tenant, string $suffix = ''): string
    {
        return "/app/{$tenant->slug}/agreements/templates{$suffix}";
    }

    // ── Sanitising + merge fields ───────────────────────────────────────────

    public function test_saving_strips_dangerous_html_but_keeps_pasted_text(): void
    {
        $t = $this->makeTenant('tpl-a');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->post($this->url($t), [
            'name' => 'Pasted from Word',
            'body_html' => '<div><p onclick="steal()">Hello <script>alert(1)</script><b>bold</b> '
                .'<a href="http://evil">link text</a></p><table><tr><td>Cell</td></tr></table></div>',
        ])->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $body = AgreementTemplate::query()->sole()->body_html;

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('alert(1)', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringNotContainsString('<a', $body);
        $this->assertStringNotContainsString('<div', $body);
        // …and none of the wording was lost.
        $this->assertStringContainsString('Hello', $body);
        $this->assertStringContainsString('<b>bold</b>', $body);
        $this->assertStringContainsString('link text', $body);
        $this->assertStringContainsString('Cell', $body);
    }

    public function test_unknown_merge_fields_and_empty_terms_are_rejected(): void
    {
        $t = $this->makeTenant('tpl-b');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')
            ->post($this->url($t), ['name' => 'Bad', 'body_html' => '<p>Hi {{customer.nickname}}</p>'])
            ->assertSessionHasErrors('body_html');

        $this->actingAs($admin, 'tenant')
            ->post($this->url($t), ['name' => 'Empty', 'body_html' => '<p>   </p><script>x()</script>'])
            ->assertSessionHasErrors('body_html');

        app()->instance('current_tenant', $t);
        $this->assertSame(0, AgreementTemplate::query()->count());
    }

    public function test_merge_values_are_escaped_into_the_frozen_terms(): void
    {
        $t = $this->makeTenant('tpl-c');
        $admin = $this->makeUser($t);
        $this->makeTemplate($t, ['body_html' => '<p>Hirer: {{customer.name}}</p>']);

        $customer = $this->makeCustomer($t, '<script>alert(1)</script>Mallory');
        $agreement = $this->createAgreement($t, $admin, ['customer' => $customer]);

        $this->assertStringNotContainsString('<script>', $agreement->terms_html);
        $this->assertStringContainsString('&lt;script&gt;', $agreement->terms_html);
    }

    // ── Selection cascade ───────────────────────────────────────────────────

    public function test_cascade_prefers_the_most_specific_template(): void
    {
        $t = $this->makeTenant('tpl-d');
        $service = app(AgreementTemplateService::class);

        $platform = $this->makeTemplate(null, ['name' => 'Platform generic']);
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_PRIVATE, 'WA')->is($platform));

        $own = $this->makeTemplate($t, ['name' => 'Own generic']);
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_PRIVATE, 'WA')->is($own));

        $byType = $this->makeTemplate($t, ['name' => 'Own private', 'agreement_type' => Agreement::TYPE_PRIVATE]);
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_PRIVATE, 'WA')->is($byType));

        $byState = $this->makeTemplate($t, ['name' => 'Own private WA', 'agreement_type' => Agreement::TYPE_PRIVATE, 'state' => 'WA']);
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_PRIVATE, 'WA')->is($byState));
        // A different state falls back to the type-only template.
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_PRIVATE, 'NSW')->is($byType));
        // A different type falls back to the generic one.
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_RIDESHARE, 'WA')->is($own));

        // Archived templates are never selected.
        $byState->update(['is_active' => false]);
        $this->assertTrue($service->resolveFor($t->id, Agreement::TYPE_PRIVATE, 'WA')->is($byType));
    }

    public function test_staff_can_pick_a_specific_template_on_the_agreement_form(): void
    {
        $t = $this->makeTenant('tpl-e');
        $admin = $this->makeUser($t);
        $this->makeTemplate($t, ['body_html' => '<p>Default wording</p>']);
        $chosen = $this->makeTemplate($t, ['body_html' => '<p>Chosen wording</p>']);

        $agreement = $this->createAgreement($t, $admin, ['payload' => ['agreement_template_id' => $chosen->id]]);

        $this->assertSame($chosen->id, $agreement->agreement_template_id);
        $this->assertStringContainsString('Chosen wording', $agreement->terms_html);

        $expected = 2 + AgreementTemplate::platformDefaults()->active()->count();
        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/create")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('templates', $expected)->has('states'));
    }

    public function test_a_company_with_no_templates_inherits_the_platform_default(): void
    {
        $t = $this->makeTenant('tpl-f');
        $admin = $this->makeUser($t);
        $platform = $this->makeTemplate(null, ['body_html' => '<p>Platform fallback wording.</p>']);

        $agreement = $this->createAgreement($t, $admin);

        $this->assertSame($platform->id, $agreement->agreement_template_id);
        $this->assertStringContainsString('Platform fallback wording.', $agreement->terms_html);
    }

    public function test_with_no_template_available_the_agreement_is_created_without_terms(): void
    {
        $t = $this->makeTenant('tpl-f2');
        $admin = $this->makeUser($t);

        // setUp() already parked the seeded defaults; this tenant has none.
        $agreement = $this->createAgreement($t, $admin);

        $this->assertNull($agreement->terms_html);
        $this->assertNull($agreement->agreement_template_id);
    }

    // ── The freeze ──────────────────────────────────────────────────────────

    public function test_editing_a_template_never_changes_an_existing_agreement(): void
    {
        $t = $this->makeTenant('tpl-g');
        $admin = $this->makeUser($t);
        $template = $this->makeTemplate($t, ['body_html' => '<p>Original wording for {{customer.name}}.</p>']);

        $agreement = $this->createAgreement($t, $admin);
        $frozen = $agreement->terms_html;

        $this->assertStringContainsString('Original wording', $frozen);
        $this->assertSame(1, $agreement->template_revision);

        $this->actingAs($admin, 'tenant')->put($this->url($t, "/{$template->id}"), [
            'name' => $template->name,
            'body_html' => '<p>REWRITTEN wording for {{customer.name}}.</p>',
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $template->fresh()->revision);
        $this->assertSame($frozen, $agreement->fresh()->terms_html);
        $this->assertSame(1, $agreement->fresh()->template_revision);
    }

    public function test_a_new_version_keeps_the_original_wording_with_updated_details(): void
    {
        $t = $this->makeTenant('tpl-h');
        $admin = $this->makeUser($t);
        $template = $this->makeTemplate($t, [
            'body_html' => '<p>Vehicle {{vehicle.registration}} hired by {{customer.name}}.</p>',
        ]);

        $vehicle = $this->makeVehicle($t, 'OLD111');
        $agreement = $this->createAgreement($t, $admin, ['vehicle' => $vehicle]);
        $this->assertStringContainsString('OLD111', $agreement->terms_html);

        // Sign, then rewrite the template — the new version must ignore the rewrite.
        $this->actingAs($admin, 'tenant')->post("/app/{$t->slug}/agreements/{$agreement->id}/sign", [
            'signature_data' => 'data:image/png;base64,iVBORw0KGgo=',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin, 'tenant')->put($this->url($t, "/{$template->id}"), [
            'name' => $template->name,
            'body_html' => '<p>COMPLETELY different terms.</p>',
        ])->assertSessionHasNoErrors();

        $this->actingAs($admin, 'tenant')->post("/app/{$t->slug}/agreements/{$agreement->id}/version")
            ->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $v2 = Agreement::query()->where('parent_agreement_id', $agreement->id)->sole();

        $this->assertStringContainsString('OLD111', $v2->terms_html);
        $this->assertStringNotContainsString('COMPLETELY different', $v2->terms_html);
        $this->assertSame(1, $v2->template_revision);
    }

    public function test_frozen_terms_reach_the_pdf_the_agreement_page_and_the_portal(): void
    {
        $t = $this->makeTenant('tpl-i');
        $admin = $this->makeUser($t);
        $this->makeTemplate($t, ['body_html' => '<h2>Clause one</h2><p>Bound by {{agreement.state}} law.</p>']);

        $agreement = $this->createAgreement($t, $admin);
        $agreement->load(['customer', 'vehicle']);

        $html = view('pdf.agreement', [
            'agreement' => $agreement,
            'money' => fn ($c) => '$'.number_format(((int) $c) / 100, 2),
            'date' => fn ($d) => $d?->format('d/m/Y'),
        ])->render();

        $this->assertStringContainsString('Terms and conditions', $html);
        $this->assertStringContainsString('Clause one', $html);
        $this->assertStringContainsString('Bound by WA law.', $html);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/{$agreement->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('terms', $agreement->terms_html)
                ->where('termsSource.revision', 1));
    }

    // ── Permissions + isolation ─────────────────────────────────────────────

    public function test_staff_may_read_templates_but_only_admin_writes(): void
    {
        $t = $this->makeTenant('tpl-j');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);
        $template = $this->makeTemplate($t);

        $this->actingAs($staff, 'tenant')->get($this->url($t))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false)->has('templates', 1));

        $this->actingAs($staff, 'tenant')->post($this->url($t), ['name' => 'X', 'body_html' => '<p>Hi</p>'])->assertForbidden();
        $this->actingAs($staff, 'tenant')->put($this->url($t, "/{$template->id}"), ['name' => 'X', 'body_html' => '<p>Hi</p>'])->assertForbidden();
        $this->actingAs($staff, 'tenant')->put($this->url($t, '/default-state'), ['default_state' => 'WA'])->assertForbidden();
    }

    public function test_platform_defaults_are_read_only_for_tenants_but_copyable(): void
    {
        $t = $this->makeTenant('tpl-k');
        $admin = $this->makeUser($t);
        $platform = $this->makeTemplate(null, ['name' => 'Platform terms', 'body_html' => '<p>Platform wording</p>']);

        $this->actingAs($admin, 'tenant')
            ->put($this->url($t, "/{$platform->id}"), ['name' => 'Hijacked', 'body_html' => '<p>Mine now</p>'])
            ->assertForbidden();
        $this->assertSame('Platform terms', $platform->fresh()->name);

        $this->actingAs($admin, 'tenant')->post($this->url($t, "/{$platform->id}/copy"))->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $copy = AgreementTemplate::query()->sole(); // tenant-scoped: only the copy
        $this->assertSame('Platform terms', $copy->name);
        $this->assertSame($t->id, (int) $copy->tenant_id);
        $this->assertStringContainsString('Platform wording', $copy->body_html);

        $this->actingAs($admin, 'tenant')
            ->put($this->url($t, "/{$copy->id}"), ['name' => 'Ours', 'body_html' => '<p>Our wording</p>'])
            ->assertSessionHasNoErrors();
        $this->assertSame('<p>Platform wording</p>', $platform->fresh()->body_html); // original untouched
    }

    public function test_tenants_cannot_touch_each_others_templates(): void
    {
        $a = $this->makeTenant('tpl-l1');
        $adminA = $this->makeUser($a);
        $templateA = $this->makeTemplate($a, ['name' => 'A terms']);

        $b = $this->makeTenant('tpl-l2');
        $adminB = $this->makeUser($b);

        $this->actingAs($adminB, 'tenant')->get($this->url($b))
            ->assertInertia(fn ($page) => $page->has('templates', 0));
        $this->actingAs($adminB, 'tenant')
            ->put($this->url($b, "/{$templateA->id}"), ['name' => 'Stolen', 'body_html' => '<p>x</p>'])
            ->assertNotFound();
        $this->actingAs($adminB, 'tenant')->post($this->url($b, "/{$templateA->id}/copy"))->assertNotFound();

        $this->assertSame('A terms', $templateA->fresh()->name);
    }

    public function test_super_admin_manages_platform_defaults_only(): void
    {
        $tenant = $this->makeTenant('tpl-m');
        $tenantTemplate = $this->makeTemplate($tenant, ['name' => 'Tenant terms']);
        $admin = $this->makeSuperAdmin();

        $this->actingAs($admin, 'superadmin')->post('/superadmin/agreement-templates', [
            'name' => 'Platform private', 'agreement_type' => Agreement::TYPE_PRIVATE, 'body_html' => '<p>Default wording</p>',
        ])->assertSessionHasNoErrors();

        $created = AgreementTemplate::platformDefaults()->where('name', 'Platform private')->sole();
        $this->assertNull($created->tenant_id);

        $this->actingAs($admin, 'superadmin')
            ->put("/superadmin/agreement-templates/{$tenantTemplate->id}", ['name' => 'Hijack', 'body_html' => '<p>x</p>'])
            ->assertNotFound();
        $this->assertSame('Tenant terms', $tenantTemplate->fresh()->name);

        $this->actingAs($admin, 'superadmin')->get('/superadmin/agreement-templates')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('SuperAdmin/AgreementTemplates/Index')->has('mergeFields'));
    }

    public function test_default_state_is_saved_and_prefills_the_agreement_form(): void
    {
        $t = $this->makeTenant('tpl-n');
        $admin = $this->makeUser($t);

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/default-state'), ['default_state' => 'WA'])
            ->assertSessionHasNoErrors();
        $this->assertSame('WA', $t->fresh()->settings['default_state']);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/create")
            ->assertInertia(fn ($page) => $page->where('defaultState', 'WA'));

        $this->actingAs($admin, 'tenant')->put($this->url($t, '/default-state'), ['default_state' => 'ZZZ'])
            ->assertSessionHasErrors('default_state');
    }

    public function test_sanitizer_drops_script_content_entirely(): void
    {
        $sanitizer = app(AgreementTermsSanitizer::class);

        $this->assertSame('<p>Safe</p>', $sanitizer->sanitize('<p>Safe</p><script>steal()</script>'));
        $this->assertTrue($sanitizer->isEmpty('<style>p{}</style>'));
        $this->assertFalse($sanitizer->isEmpty('<p>Something</p>'));
    }
}
