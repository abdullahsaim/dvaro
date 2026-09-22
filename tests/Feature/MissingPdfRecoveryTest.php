<?php

namespace Tests\Feature;

use App\Jobs\GenerateAgreementPdfJob;
use App\Jobs\GenerateInvoicePdfJob;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Recovering documents whose PDF never generated.
 *
 * PDF generation is queued, so an agreement signed while no worker was running
 * says "the PDF is being generated" forever — the job is gone and only a fresh
 * dispatch brings it back.
 *
 * The rule that shapes all of this: a PDF is recovered only when ABSENT. An
 * agreement's PDF is written once at signing and kept, so a company that
 * rebrands later must never be able to re-render a document someone has already
 * signed.
 */
class MissingPdfRecoveryTest extends TestCase
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
            'name' => ucfirst($slug).' Rentals', 'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE, 'plan_id' => $plan->id,
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

    private function agreement(Tenant $tenant, ?string $pdfPath = null): Agreement
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Renter '.uniqid(), 'email' => 'r-'.uniqid().'@test.au', 'phone' => '0400000000',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);

        $vehicle = new Vehicle([
            'registration_number' => 'PDF'.random_int(100, 999), 'make' => 'Toyota', 'model' => 'Camry',
            'year' => 2023, 'status' => Vehicle::STATUS_RENTED, 'daily_rate' => 9000,
        ]);
        $vehicle->save();

        $agreement = Agreement::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'private',
            'status' => Agreement::STATUS_SIGNED,
            'billing_cycle' => 'weekly',
            'rate' => 90000,
            'bond_amount' => 50000,
            'start_date' => today(),
            'version' => 1,
        ]);

        if ($pdfPath !== null) {
            $agreement->update(['pdf_path' => $pdfPath]);
        }

        return $agreement->fresh();
    }

    private function invoice(Tenant $tenant, ?string $pdfPath = null): Invoice
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Renter '.uniqid(), 'email' => 'i-'.uniqid().'@test.au', 'phone' => '0400000000',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);

        return Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => today(), 'due_date' => today()->addDays(7),
            'billing_period_start' => today(), 'billing_period_end' => today()->addMonth(),
            'subtotal' => 10000, 'total' => 10000, 'paid_amount' => 0,
            'pdf_path' => $pdfPath,
        ]);
    }

    // ── The button ─────────────────────────────────────────────────────────

    public function test_the_rebuild_option_is_offered_only_when_the_pdf_is_missing(): void
    {
        $disk = Storage::fake('local');
        $t = $this->makeTenant('pdfrec-a');
        $admin = $this->user($t);
        $missing = $this->agreement($t);

        $path = "tenants/{$t->id}/agreements/1/agreement-v1.pdf";
        $disk->put($path, '%PDF-1.4'); // the file must genuinely exist to count as "has one"
        $has = $this->agreement($t, $path);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/{$missing->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRebuildPdf', true));

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/{$has->id}")
            ->assertInertia(fn ($page) => $page->where('canRebuildPdf', false));
    }

    public function test_rebuilding_queues_the_job(): void
    {
        Queue::fake();
        $t = $this->makeTenant('pdfrec-b');
        $admin = $this->user($t);
        $agreement = $this->agreement($t);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/pdf")
            ->assertSessionHasNoErrors();

        Queue::assertPushedOn('pdf', GenerateAgreementPdfJob::class);
    }

    public function test_an_existing_pdf_is_never_re_rendered(): void
    {
        Queue::fake();
        $disk = Storage::fake('local');
        $t = $this->makeTenant('pdfrec-c');
        $admin = $this->user($t);

        $path = "tenants/{$t->id}/agreements/1/agreement-v1.pdf";
        $disk->put($path, '%PDF-1.4');
        $agreement = $this->agreement($t, $path);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/pdf")
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_a_path_pointing_at_a_deleted_file_can_be_rebuilt(): void
    {
        Queue::fake();
        Storage::fake('local'); // the path is recorded but the file is not there
        $t = $this->makeTenant('pdfrec-d');
        $admin = $this->user($t);
        $agreement = $this->agreement($t, "tenants/{$t->id}/agreements/9/agreement-v1.pdf");

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/pdf")
            ->assertSessionHasNoErrors();

        Queue::assertPushedOn('pdf', GenerateAgreementPdfJob::class);
    }

    public function test_staff_cannot_rebuild_and_other_companies_get_a_404(): void
    {
        Queue::fake();
        $a = $this->makeTenant('pdfrec-e1');
        $staff = $this->user($a, TenantUser::ROLE_STAFF);
        $agreementA = $this->agreement($a);

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$a->slug}/agreements/{$agreementA->id}/pdf")
            ->assertForbidden();

        $b = $this->makeTenant('pdfrec-e2');
        $adminB = $this->user($b);

        $this->actingAs($adminB, 'tenant')
            ->post("/app/{$b->slug}/agreements/{$agreementA->id}/pdf")
            ->assertNotFound();

        Queue::assertNothingPushed();
    }

    // ── The actual live bug: a recorded path whose file is missing ─────────
    //
    // pdf_path being set does NOT mean the file exists — a row can outlive
    // its file (a deploy that never carried storage/ across, a deleted file).
    // Storage::download() on a missing file throws an UNCAUGHT exception,
    // which is exactly the "server error" this reproduces and fixes.

    public function test_downloading_a_recorded_but_missing_agreement_pdf_404s_cleanly(): void
    {
        Storage::fake('local'); // path recorded in DB, no file behind it
        $t = $this->makeTenant('pdfrec-crash-a');
        $admin = $this->user($t);
        $agreement = $this->agreement($t, "tenants/{$t->id}/agreements/1/agreement-v1.pdf");

        $this->actingAs($admin, 'tenant')
            ->get("/app/{$t->slug}/agreements/{$agreement->id}/pdf")
            ->assertNotFound(); // never a 500
    }

    public function test_downloading_a_recorded_but_missing_invoice_pdf_404s_cleanly(): void
    {
        Storage::fake('local');
        $t = $this->makeTenant('pdfrec-crash-b');
        $admin = $this->user($t);
        $invoice = $this->invoice($t, "tenants/{$t->id}/invoices/1/invoice.pdf");

        $this->actingAs($admin, 'tenant')
            ->get("/app/{$t->slug}/invoices/{$invoice->id}/pdf")
            ->assertNotFound();
    }

    public function test_the_agreement_page_offers_to_rebuild_when_the_file_is_missing_even_though_pdf_path_is_set(): void
    {
        Storage::fake('local');
        $t = $this->makeTenant('pdfrec-crash-c');
        $admin = $this->user($t);
        $agreement = $this->agreement($t, "tenants/{$t->id}/agreements/1/agreement-v1.pdf");

        // Before the fix this stayed false whenever pdf_path was non-null,
        // so the page showed a download link that would 500 when clicked.
        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/{$agreement->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('pdfReady', false)
                ->where('canRebuildPdf', true));
    }

    public function test_the_agreement_page_hides_rebuild_once_the_file_genuinely_exists(): void
    {
        $disk = Storage::fake('local');
        $t = $this->makeTenant('pdfrec-crash-d');
        $admin = $this->user($t);

        $path = "tenants/{$t->id}/agreements/2/agreement-v1.pdf";
        $disk->put($path, '%PDF-1.4');
        $agreement = $this->agreement($t, $path);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/{$agreement->id}")
            ->assertInertia(fn ($page) => $page
                ->where('pdfReady', true)
                ->where('canRebuildPdf', false));
    }

    public function test_the_invoice_page_hides_the_download_link_when_the_file_is_missing(): void
    {
        Storage::fake('local');
        $t = $this->makeTenant('pdfrec-crash-e');
        $admin = $this->user($t);
        $invoice = $this->invoice($t, "tenants/{$t->id}/invoices/2/invoice.pdf");

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('pdfReady', false));
    }

    // ── The command ────────────────────────────────────────────────────────

    public function test_the_command_queues_only_what_is_missing(): void
    {
        Queue::fake();
        Storage::fake('local');
        $t = $this->makeTenant('pdfrec-cmd');

        $this->agreement($t);                       // missing
        $this->agreement($t, 'tenants/x/gone.pdf'); // recorded but the file is gone
        $this->invoice($t);                         // missing

        app()->forgetInstance('current_tenant');

        $this->artisan('pdfs:generate-missing', ['--tenant' => $t->slug])
            ->assertSuccessful();

        Queue::assertPushed(GenerateAgreementPdfJob::class, 2);
        Queue::assertPushed(GenerateInvoicePdfJob::class, 1);
    }

    public function test_a_dry_run_dispatches_nothing(): void
    {
        Queue::fake();
        Storage::fake('local');
        $t = $this->makeTenant('pdfrec-dry');
        $this->agreement($t);

        app()->forgetInstance('current_tenant');

        $this->artisan('pdfs:generate-missing', ['--tenant' => $t->slug, '--dry-run' => true])
            ->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_the_type_option_narrows_the_sweep(): void
    {
        Queue::fake();
        Storage::fake('local');
        $t = $this->makeTenant('pdfrec-type');
        $this->agreement($t);
        $this->invoice($t);

        app()->forgetInstance('current_tenant');

        $this->artisan('pdfs:generate-missing', ['--tenant' => $t->slug, '--type' => 'invoices'])
            ->assertSuccessful();

        Queue::assertPushed(GenerateInvoicePdfJob::class, 1);
        Queue::assertNotPushed(GenerateAgreementPdfJob::class);
    }

    public function test_an_unknown_tenant_or_type_fails_loudly(): void
    {
        $this->artisan('pdfs:generate-missing', ['--tenant' => 'no-such-company'])->assertFailed();
        $this->artisan('pdfs:generate-missing', ['--type' => 'receipts'])->assertFailed();
    }

    public function test_the_command_leaves_documents_that_already_have_a_pdf_alone(): void
    {
        Queue::fake();
        $disk = Storage::fake('local');
        $t = $this->makeTenant('pdfrec-ok');

        $path = "tenants/{$t->id}/agreements/5/agreement-v1.pdf";
        $disk->put($path, '%PDF-1.4');
        $this->agreement($t, $path);

        app()->forgetInstance('current_tenant');

        $this->artisan('pdfs:generate-missing', ['--tenant' => $t->slug])->assertSuccessful();

        Queue::assertNothingPushed();
    }
}
