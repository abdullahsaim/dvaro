<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Client feedback: "payment values me gst huna chahye" — the invoice page
 * stated no GST at all, even though the PDF already did (1/11 of a
 * GST-inclusive total, InvoiceTemplateService::gstOf). This surfaces the same
 * figure on screen: a GST line in the totals, gated on the company's own
 * gst_registered setting (Settings → Invoices) — never shown for a company
 * that isn't registered.
 */
class InvoiceGstDisplayTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, bool $gstRegistered = true): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE,
            'settings' => ['gst_registered' => $gstRegistered],
        ]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeUser(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function makeInvoice(Tenant $tenant, int $total = 110000): Invoice
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
            'issue_date' => today(), 'due_date' => today()->addDays(7),
            'billing_period_start' => today(), 'billing_period_end' => today()->addMonth(),
            'subtotal' => $total, 'total' => $total, 'paid_amount' => 0,
        ]);
    }

    public function test_gst_registered_company_sees_gst_on_the_total(): void
    {
        $t = $this->makeTenant('gst-on', gstRegistered: true);
        $admin = $this->makeUser($t);
        $invoice = $this->makeInvoice($t, 110000); // $1,100.00 inc GST → $100 GST

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/invoices/{$invoice->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('gstRegistered', true)
                ->where('gstOnTotal', 10000));
    }

    public function test_a_company_not_registered_sees_no_gst_figure_at_all(): void
    {
        $t = $this->makeTenant('gst-off', gstRegistered: false);
        $admin = $this->makeUser($t);
        $invoice = $this->makeInvoice($t);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/invoices/{$invoice->id}")
            ->assertInertia(fn ($page) => $page
                ->where('gstRegistered', false)
                ->where('gstOnTotal', null));
    }

    public function test_gst_is_a_statement_about_the_total_never_added_to_it(): void
    {
        $t = $this->makeTenant('gst-math');
        $admin = $this->makeUser($t);
        $invoice = $this->makeInvoice($t, 55000); // $550.00 → $50.00 GST (rounded)

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/invoices/{$invoice->id}")
            ->assertInertia(fn ($page) => $page->where('gstOnTotal', 5000));

        // The stored total is untouched by any of this.
        $this->assertSame(55000, $invoice->fresh()->total);
    }
}
