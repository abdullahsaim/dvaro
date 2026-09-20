<?php

namespace Tests\Feature;

use App\Jobs\GenerateInvoicePdfJob;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Invoice\Services\InvoiceTemplateService;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use App\Services\TenantBranding;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Invoice templates (client feedback #4): four ready-made layouts, the
 * company's logo and colour, and the wording around the numbers.
 *
 * The rule under test throughout: a template decides PRESENTATION and nothing
 * else. Every layout must show the same figures, and no setting may change what
 * a customer owes.
 */
class InvoiceTemplateTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $settings = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug, 'slug' => 'plan-'.$slug.'-'.uniqid(),
            'price_monthly' => 5000, 'price_annual' => 50000, 'is_active' => true, 'is_free' => false,
            'trial_days' => 14, 'modules' => Plan::MODULE_KEYS, 'limits' => [], 'sort_order' => 0,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug).' Rentals', 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id, 'settings' => $settings ?: null,
        ]);

        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function user(Tenant $tenant, string $role = TenantUser::ROLE_ADMIN): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Staffer', 'email' => $role.'-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => $role, 'is_active' => true,
        ]);
    }

    private function url(Tenant $tenant, string $suffix = ''): string
    {
        return "/app/{$tenant->slug}/settings/invoice-template{$suffix}";
    }

    /** A saved invoice with two line items, so the PDF has something to draw. */
    private function makeInvoice(Tenant $tenant, int $total = 154000): Invoice
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Renter', 'email' => 'renter-'.uniqid().'@test.au', 'phone' => '0400000000',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_RECURRING,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => today(),
            'due_date' => today()->addDays(7),
            'billing_period_start' => today(),
            'billing_period_end' => today()->addMonth(),
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => 0,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => 'Weekly rental',
            'amount' => $total,
        ]);

        return $invoice->fresh(['items', 'payments', 'customer']);
    }

    /** Render the real PDF Blade to HTML (what dompdf is handed). */
    private function render(Tenant $tenant, Invoice $invoice): string
    {
        return view('pdf.invoice', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'template' => app(InvoiceTemplateService::class)->resolve($tenant),
        ])->render();
    }

    // ── Every layout renders, and they all agree on the numbers ─────────────

    public function test_all_four_layouts_render_the_same_figures(): void
    {
        foreach (InvoiceTemplateService::LAYOUTS as $layout) {
            $tenant = $this->makeTenant('tpl-'.$layout, [
                'invoice_template' => ['layout' => $layout],
                'abn' => '12345678901',
            ]);
            $invoice = $this->makeInvoice($tenant, 154000);

            $html = $this->render($tenant, $invoice);

            $this->assertStringContainsString('$1,540.00', $html, "{$layout} shows the total");
            $this->assertStringContainsString('12345678901', $html, "{$layout} shows the ABN");
            $this->assertStringContainsString('Weekly rental', $html, "{$layout} lists the items");
            $this->assertStringContainsString('<!DOCTYPE html>', $html);
        }
    }

    public function test_an_unconfigured_company_still_gets_a_working_invoice(): void
    {
        $tenant = $this->makeTenant('tpl-bare'); // no settings at all
        $invoice = $this->makeInvoice($tenant);

        $html = $this->render($tenant, $invoice);

        $this->assertStringContainsString('Tax Invoice', $html);
        $this->assertStringContainsString('Tpl-bare Rentals', $html);
        $this->assertStringNotContainsString('<img', $html); // no logo configured
    }

    public function test_a_junk_layout_falls_back_instead_of_failing(): void
    {
        $tenant = $this->makeTenant('tpl-junk', ['invoice_template' => ['layout' => 'wingdings']]);

        $this->assertSame(
            InvoiceTemplateService::LAYOUT_CLASSIC,
            app(InvoiceTemplateService::class)->resolve($tenant)['layout'],
        );
    }

    // ── Branding ───────────────────────────────────────────────────────────

    public function test_colour_and_wording_reach_the_document(): void
    {
        $tenant = $this->makeTenant('tpl-brand', [
            'brand_colour' => '#123456',
            'invoice_template' => [
                'layout' => InvoiceTemplateService::LAYOUT_MODERN,
                'title' => 'Rental Invoice',
                'intro' => 'Thanks for renting with us.',
                'payment_instructions' => "BSB 123-456\nAccount 12345678",
                'thank_you' => 'See you on the road.',
            ],
        ]);
        $invoice = $this->makeInvoice($tenant);

        $html = $this->render($tenant, $invoice);

        $this->assertStringContainsString('#123456', $html);
        $this->assertStringContainsString('Rental Invoice', $html);
        $this->assertStringContainsString('Thanks for renting with us.', $html);
        $this->assertStringContainsString('BSB 123-456', $html);
        $this->assertStringContainsString('See you on the road.', $html);
        // Newlines in the multi-line fields survive as line breaks.
        $this->assertStringContainsString('<br />', $html);
    }

    public function test_the_invoice_logo_wins_over_the_company_logo_and_both_are_optional(): void
    {
        $disk = Storage::fake('public');
        $disk->put('tenants/1/branding/company.png', 'x');
        $disk->put('tenants/1/branding/invoice.png', 'y');

        $tenant = $this->makeTenant('tpl-logo', [
            'logo_path' => 'tenants/1/branding/company.png',
            'invoice_template' => ['logo_path' => 'tenants/1/branding/invoice.png'],
        ]);
        $service = app(InvoiceTemplateService::class);

        $this->assertStringContainsString('invoice.png', $service->resolve($tenant)['logo']);

        // Falls back to the company logo…
        $tenant->settings = [...$tenant->settings, 'invoice_template' => ['logo_path' => null]];
        $tenant->save();
        $this->assertStringContainsString('company.png', $service->resolve($tenant)['logo']);

        // …and is dropped entirely when the switch is off.
        $tenant->settings = [...$tenant->settings, 'invoice_template' => ['show_logo' => false]];
        $tenant->save();
        $this->assertNull($service->resolve($tenant)['logo']);
    }

    public function test_the_logo_is_a_file_path_for_the_pdf_and_a_url_on_screen(): void
    {
        $disk = Storage::fake('public');
        $disk->put('tenants/1/branding/company.png', 'x');

        $tenant = $this->makeTenant('tpl-logo-url', ['logo_path' => 'tenants/1/branding/company.png']);
        $service = app(InvoiceTemplateService::class);

        // dompdf reads the file off disk…
        $this->assertStringContainsString(
            DIRECTORY_SEPARATOR,
            $service->resolve($tenant)['logo'],
        );

        // …a browser cannot, so the preview gets a root-relative URL (never an
        // APP_URL-derived absolute one, which drops the port in local dev).
        $screen = $service->resolve($tenant, forScreen: true)['logo'];
        $this->assertStringStartsWith('/', $screen);
        $this->assertStringNotContainsString('http', $screen);
        $this->assertStringEndsWith('tenants/1/branding/company.png', $screen);
    }

    public function test_a_logo_path_that_escapes_the_disk_is_refused(): void
    {
        Storage::fake('public');

        $tenant = $this->makeTenant('tpl-escape', [
            'invoice_template' => ['logo_path' => '../../../.env'],
        ]);

        $this->assertNull(app(InvoiceTemplateService::class)->resolve($tenant)['logo']);
    }

    public function test_a_missing_logo_file_does_not_break_the_invoice(): void
    {
        Storage::fake('public');

        $tenant = $this->makeTenant('tpl-gone', ['logo_path' => 'tenants/9/branding/deleted.png']);
        $invoice = $this->makeInvoice($tenant);

        $this->assertNull(app(InvoiceTemplateService::class)->resolve($tenant)['logo']);
        $this->assertStringContainsString('$1,540.00', $this->render($tenant, $invoice));
    }

    // ── GST ────────────────────────────────────────────────────────────────

    public function test_gst_is_stated_as_a_share_of_the_total_not_added_to_it(): void
    {
        $tenant = $this->makeTenant('tpl-gst', ['gst_registered' => true, 'abn' => '11222333444']);
        $invoice = $this->makeInvoice($tenant, 110000); // $1,100.00 inc GST

        $html = $this->render($tenant, $invoice);

        $this->assertStringContainsString('includes GST of', $html);
        $this->assertStringContainsString('$100.00', $html); // 1/11 of $1,100
        $this->assertStringContainsString('$1,100.00', $html);
        // The stored amount is untouched by any of this.
        $this->assertSame(110000, $invoice->fresh()->total);
    }

    public function test_a_company_not_registered_for_gst_issues_a_plain_invoice(): void
    {
        $tenant = $this->makeTenant('tpl-nogst', ['gst_registered' => false]);
        $invoice = $this->makeInvoice($tenant);

        $html = $this->render($tenant, $invoice);

        $this->assertStringNotContainsString('includes GST', $html);
        $this->assertStringNotContainsString('Tax Invoice', $html);
        $this->assertStringContainsString('Invoice', $html);
    }

    public function test_gst_rounds_to_the_cent(): void
    {
        $service = app(InvoiceTemplateService::class);

        $this->assertSame(10000, $service->gstOf(110000));
        $this->assertSame(909, $service->gstOf(10000));   // $100.00 → $9.09
        $this->assertSame(0, $service->gstOf(0));
    }

    // ── Sanitising ─────────────────────────────────────────────────────────

    public function test_markup_never_survives_into_a_customers_pdf(): void
    {
        $clean = app(InvoiceTemplateService::class)->sanitize([
            'layout' => 'modern',
            'accent_colour' => 'red',
            'title' => '<script>alert(1)</script>Invoice',
            'intro' => "<b>Bold</b> text\n\n\n\nwith gaps",
            'thank_you' => str_repeat('x', 500),
        ]);

        $this->assertSame('modern', $clean['layout']);
        $this->assertNull($clean['accent_colour']); // not a hex colour
        $this->assertSame('alert(1)Invoice', $clean['title']);
        $this->assertSame("Bold text\n\nwith gaps", $clean['intro']);
        $this->assertSame(120, mb_strlen($clean['thank_you']));
    }

    public function test_a_tenants_wording_is_escaped_when_rendered(): void
    {
        $tenant = $this->makeTenant('tpl-escape2', [
            'invoice_template' => ['intro' => 'Pay us <b>now</b>'],
        ]);
        $invoice = $this->makeInvoice($tenant);

        $html = $this->render($tenant, $invoice);

        // sanitize() strips the tags on save; the renderer escapes whatever is
        // left, so no stored value can ever inject markup.
        $this->assertStringNotContainsString('Pay us <b>now</b>', $html);
    }

    // ── The screen ─────────────────────────────────────────────────────────

    public function test_the_settings_screen_renders_and_saves(): void
    {
        $tenant = $this->makeTenant('tpl-screen');
        $admin = $this->user($tenant);

        $this->actingAs($admin, 'tenant')->get($this->url($tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Settings/InvoiceTemplate')
                ->where('settings.layout', 'classic')
                ->where('canManage', true)
                ->where('hasAbn', false)
                ->has('layouts', 4));

        $this->actingAs($admin, 'tenant')->put($this->url($tenant), [
            'layout' => 'minimal',
            'accent_colour' => '#aa3344',
            'show_logo' => 1,
            'show_company_details' => 0,
            'gst_registered' => 1,
            'title' => 'Rental Invoice',
            'intro' => '',
            'payment_instructions' => 'BSB 000-111',
            'footer_note' => '',
            'thank_you' => 'Thanks!',
        ])->assertSessionHasNoErrors();

        $stored = app(TenantSettingsService::class)->get($tenant->fresh(), 'invoice_template');

        $this->assertSame('minimal', $stored['layout']);
        $this->assertSame('#aa3344', $stored['accent_colour']);
        $this->assertFalse($stored['show_company_details']);
        $this->assertSame('BSB 000-111', $stored['payment_instructions']);
        $this->assertNull($stored['intro']);

        // Changing the design is audited like every other setting.
        app()->instance('current_tenant', $tenant->fresh());
        $this->assertSame(1, AuditLog::where('action', 'settings.invoices.updated')->count());
    }

    public function test_the_form_rejects_junk(): void
    {
        $tenant = $this->makeTenant('tpl-invalid');
        $admin = $this->user($tenant);

        $this->actingAs($admin, 'tenant')->put($this->url($tenant), [
            'layout' => 'papyrus',
            'accent_colour' => 'blue',
            'show_logo' => 1,
            'show_company_details' => 1,
            'gst_registered' => 1,
            'title' => str_repeat('x', 200),
        ])->assertSessionHasErrors(['layout', 'accent_colour', 'title']);
    }

    public function test_the_preview_renders_the_chosen_layout_without_saving_anything(): void
    {
        $tenant = $this->makeTenant('tpl-preview', [
            'invoice_template' => ['layout' => 'compact', 'title' => 'Preview Me'],
        ]);
        $admin = $this->user($tenant);

        $before = Invoice::query()->count();

        $response = $this->actingAs($admin, 'tenant')->get($this->url($tenant, '/preview'));

        $response->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'");

        $html = $response->getContent();
        $this->assertStringContainsString('Preview Me', $html);
        $this->assertStringContainsString('Sample Customer', $html);

        app()->instance('current_tenant', $tenant);
        $this->assertSame($before, Invoice::query()->count(), 'the preview must not write an invoice');
    }

    public function test_uploading_and_removing_an_invoice_logo(): void
    {
        $disk = Storage::fake('public');
        $tenant = $this->makeTenant('tpl-upload');
        $admin = $this->user($tenant);

        $this->actingAs($admin, 'tenant')
            ->post($this->url($tenant, '/logo'), ['logo' => UploadedFile::fake()->image('inv.png', 300, 90)])
            ->assertSessionHasNoErrors();

        $first = app(InvoiceTemplateService::class)->forUi($tenant->fresh())['logo_path'];
        $this->assertStringStartsWith("tenants/{$tenant->id}/branding/invoice-logo-", $first);
        $disk->assertExists($first);

        // Replacing deletes the old file.
        $this->actingAs($admin, 'tenant')
            ->post($this->url($tenant, '/logo'), ['logo' => UploadedFile::fake()->image('new.png')]);
        $second = app(InvoiceTemplateService::class)->forUi($tenant->fresh())['logo_path'];
        $disk->assertMissing($first);
        $disk->assertExists($second);

        $this->actingAs($admin, 'tenant')->delete($this->url($tenant, '/logo'))->assertSessionHasNoErrors();
        $this->assertNull(app(InvoiceTemplateService::class)->forUi($tenant->fresh())['logo_path']);
        $this->assertCount(0, $disk->allFiles());
    }

    public function test_saving_the_form_does_not_drop_the_uploaded_logo(): void
    {
        Storage::fake('public');
        $tenant = $this->makeTenant('tpl-keep');
        $admin = $this->user($tenant);

        $this->actingAs($admin, 'tenant')
            ->post($this->url($tenant, '/logo'), ['logo' => UploadedFile::fake()->image('inv.png')]);
        $logo = app(InvoiceTemplateService::class)->forUi($tenant->fresh())['logo_path'];

        $this->actingAs($admin, 'tenant')->put($this->url($tenant), [
            'layout' => 'modern', 'show_logo' => 1, 'show_company_details' => 1, 'gst_registered' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertSame($logo, app(InvoiceTemplateService::class)->forUi($tenant->fresh())['logo_path']);
    }

    // ── Permissions and isolation ──────────────────────────────────────────

    public function test_staff_may_look_but_not_change(): void
    {
        $tenant = $this->makeTenant('tpl-perms');
        $staff = $this->user($tenant, TenantUser::ROLE_STAFF);
        $accounts = $this->user($tenant, TenantUser::ROLE_ACCOUNTS);

        $this->actingAs($staff, 'tenant')->get($this->url($tenant))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canManage', false));

        $this->actingAs($staff, 'tenant')->put($this->url($tenant), [
            'layout' => 'modern', 'show_logo' => 1, 'show_company_details' => 1, 'gst_registered' => 1,
        ])->assertForbidden();

        // Accounts owns invoicing, so accounts may change it.
        $this->actingAs($accounts, 'tenant')->put($this->url($tenant), [
            'layout' => 'modern', 'show_logo' => 1, 'show_company_details' => 1, 'gst_registered' => 1,
        ])->assertSessionHasNoErrors();
    }

    public function test_one_companys_design_never_reaches_anothers_invoice(): void
    {
        $a = $this->makeTenant('tpl-a', [
            'invoice_template' => ['layout' => 'minimal', 'title' => 'A Invoice'],
            'brand_colour' => '#ff0000',
        ]);
        $b = $this->makeTenant('tpl-b');
        $invoiceB = $this->makeInvoice($b);

        $html = $this->render($b, $invoiceB);

        $this->assertStringNotContainsString('A Invoice', $html);
        $this->assertStringNotContainsString('#ff0000', $html);
        $this->assertSame('classic', app(InvoiceTemplateService::class)->resolve($b)['layout']);
    }

    // ── Regenerating an older invoice ──────────────────────────────────────

    public function test_regenerating_requeues_the_pdf_without_touching_the_money(): void
    {
        Queue::fake();
        $tenant = $this->makeTenant('tpl-regen');
        $accounts = $this->user($tenant, TenantUser::ROLE_ACCOUNTS);
        $invoice = $this->makeInvoice($tenant, 99000);

        $this->actingAs($accounts, 'tenant')
            ->post("/app/{$tenant->slug}/invoices/{$invoice->id}/pdf")
            ->assertSessionHasNoErrors();

        Queue::assertPushedOn('pdf', GenerateInvoicePdfJob::class);

        $fresh = $invoice->fresh();
        $this->assertSame(99000, $fresh->total);
        $this->assertSame(0, $fresh->paid_amount);
    }

    public function test_only_admin_or_accounts_may_regenerate(): void
    {
        Queue::fake();
        $tenant = $this->makeTenant('tpl-regen2');
        $staff = $this->user($tenant, TenantUser::ROLE_STAFF);
        $invoice = $this->makeInvoice($tenant);

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$tenant->slug}/invoices/{$invoice->id}/pdf")
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_another_companys_invoice_cannot_be_regenerated(): void
    {
        Queue::fake();
        $a = $this->makeTenant('tpl-x');
        $invoiceA = $this->makeInvoice($a);

        $b = $this->makeTenant('tpl-y');
        $adminB = $this->user($b);

        $this->actingAs($adminB, 'tenant')
            ->post("/app/{$b->slug}/invoices/{$invoiceA->id}/pdf")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    // ── The same letterhead on agreements ──────────────────────────────────

    public function test_agreements_carry_the_same_company_letterhead(): void
    {
        $disk = Storage::fake('public');
        $disk->put('tenants/3/branding/company.png', 'x');

        $tenant = $this->makeTenant('tpl-agr', [
            'logo_path' => 'tenants/3/branding/company.png',
            'legal_name' => 'Coastline Vehicle Rentals Pty Ltd',
            'abn' => '51824753556',
            'address' => '12 Beach Road, Canning Vale WA',
            'brand_colour' => '#0d6d5b',
            'date_format' => 'd M Y',
        ]);

        $branding = app(TenantBranding::class)->forDocument($tenant);

        $agreement = new Agreement([
            'tenant_id' => $tenant->id,
            'type' => 'private',
            'status' => 'signed',
            'billing_cycle' => 'weekly',
            'rate' => 98000,
            'bond_amount' => 50000,
            'start_date' => today(),
        ]);
        $agreement->id = 77;
        $agreement->version = 2;
        $agreement->exists = true;
        $agreement->setRelation('customer', new Customer(['name' => 'Priya Raghavan']));
        $agreement->setRelation('vehicle', null);

        $html = view('pdf.agreement', ['agreement' => $agreement, 'branding' => $branding])->render();

        $this->assertStringContainsString('Coastline Vehicle Rentals Pty Ltd', $html);
        $this->assertStringContainsString('trading as Tpl-agr Rentals', $html);
        $this->assertStringContainsString('ABN 51824753556', $html);
        $this->assertStringContainsString('#0d6d5b', $html);
        $this->assertStringContainsString('company.png', $html);
        // The company's date format is honoured here too.
        $this->assertStringContainsString(today()->format('d M Y'), $html);
        // Content is untouched by any of it.
        $this->assertStringContainsString('Rental Agreement', $html);
        $this->assertStringContainsString('Priya Raghavan', $html);
        $this->assertStringContainsString('$980.00', $html);
    }

    public function test_the_agreement_pdf_still_renders_without_any_branding(): void
    {
        // The Blade is rendered directly in other tests and by older callers
        // that pass no branding at all — it must not fall over.
        $tenant = $this->makeTenant('tpl-agr2');

        $agreement = new Agreement([
            'tenant_id' => $tenant->id, 'type' => 'private', 'status' => 'draft',
            'billing_cycle' => 'weekly', 'rate' => 1000, 'bond_amount' => 0, 'start_date' => today(),
        ]);
        $agreement->id = 78;
        $agreement->version = 1;
        $agreement->exists = true;
        $agreement->setRelation('customer', null);
        $agreement->setRelation('vehicle', null);

        $html = view('pdf.agreement', ['agreement' => $agreement])->render();

        $this->assertStringContainsString('Rental Agreement', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_the_pdf_job_renders_the_chosen_template(): void
    {
        Storage::fake('local');
        $tenant = $this->makeTenant('tpl-job', [
            'invoice_template' => ['layout' => 'modern', 'title' => 'Job Invoice'],
        ]);
        $invoice = $this->makeInvoice($tenant);

        app()->forgetInstance('current_tenant');
        (new GenerateInvoicePdfJob((int) $invoice->id, (int) $tenant->id))->handle();

        app()->instance('current_tenant', $tenant);
        $path = $invoice->fresh()->pdf_path;

        $this->assertNotNull($path, 'the job should record a pdf_path');
        $contents = Storage::disk(config('filesystems.default'))->get($path);
        $this->assertStringStartsWith('%PDF', $contents);
    }
}
