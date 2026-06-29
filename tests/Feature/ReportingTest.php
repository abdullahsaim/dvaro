<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExportJob;
use App\Modules\Customer\Models\Customer;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Reporting & Analytics.
 *
 * Covers the security-critical surface (cross-tenant revenue isolation), the
 * Australian-FY boundary logic, and the always-queued export path.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class ReportingTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug,
            'slug' => 'plan-'.$slug.'-'.Str::random(5),
            'price_monthly' => 0,
            'price_annual' => 0,
            'is_active' => true,
            'is_free' => true,
            'trial_days' => 14,
            'modules' => [],
            'limits' => [],
            'sort_order' => 1,
        ]);

        $tenant = Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id,
            'settings' => [],
        ]);

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
            'tenant_id' => $tenant->id,
            'name' => 'User '.$email,
            'email' => $email,
            'password' => 'secret123',
            'role' => TenantUser::ROLE_ADMIN,
        ]);
    }

    /**
     * Create a paid invoice + payment of $amountCents for a tenant, paid on
     * $paidAt. Returns nothing — the side effects are what the report reads.
     */
    private function makePaidInvoice(Tenant $tenant, int $amountCents, Carbon $paidAt): void
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Customer '.Str::random(4),
            'email' => Str::random(6).'@example.test',
            'phone' => '0400000000',
            'licence_number' => '',
            'address' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'risk_notes' => '',
            'is_blacklisted' => false,
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_RECURRING,
            'status' => Invoice::STATUS_PAID,
            'billing_period_start' => $paidAt->copy()->subDays(7),
            'billing_period_end' => $paidAt,
            'due_date' => $paidAt,
            'subtotal' => $amountCents,
            'total' => $amountCents,
            'paid_amount' => $amountCents,
        ]);

        Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => $amountCents,
            'method' => Payment::METHOD_CASH,
            'paid_at' => $paidAt,
        ]);
    }

    public function test_revenue_report_only_includes_current_tenants_data(): void
    {
        $tenantA = $this->makeTenant('rev-a');
        $tenantB = $this->makeTenant('rev-b');

        $paidAt = Carbon::create(2026, 3, 15, 12);

        $this->makePaidInvoice($tenantA, 50000, $paidAt);  // A: $500.00
        $this->makePaidInvoice($tenantB, 99999, $paidAt);  // B: $999.99 — must NOT leak

        // Explicit range around the payment date (clock-independent).
        $from = $paidAt->copy()->startOfMonth();
        $to = $paidAt->copy()->endOfMonth();

        // Bind tenant A and read its revenue.
        app()->instance('current_tenant', $tenantA);
        $revenue = app(ReportingService::class)->revenueByPeriod($from, $to);

        $total = array_sum(array_column($revenue, 'revenue'));
        $this->assertSame(50000, $total, 'Tenant A revenue should be exactly its own.');
        $this->assertNotContains(99999, array_column($revenue, 'revenue'), "Tenant B's revenue must never appear.");

        // And tenant B sees only its own.
        app()->instance('current_tenant', $tenantB);
        $revenueB = app(ReportingService::class)->revenueByPeriod($from, $to);
        $this->assertSame(99999, array_sum(array_column($revenueB, 'revenue')));
    }

    public function test_australian_fy_resolves_correctly_either_side_of_july(): void
    {
        $service = app(ReportingService::class);

        // BEFORE 1 July 2025 → FY 2024-07-01 .. 2025-06-30.
        Carbon::setTestNow(Carbon::create(2025, 6, 20, 12, 0, 0, 'Australia/Sydney'));
        $fy = $service->australianFY();
        $this->assertSame('2024-07-01', $fy['from']->toDateString());
        $this->assertSame('2025-06-30', $fy['to']->toDateString());

        // AFTER 1 July 2025 → FY 2025-07-01 .. 2026-06-30.
        Carbon::setTestNow(Carbon::create(2025, 7, 10, 12, 0, 0, 'Australia/Sydney'));
        $fy = $service->australianFY();
        $this->assertSame('2025-07-01', $fy['from']->toDateString());
        $this->assertSame('2026-06-30', $fy['to']->toDateString());

        Carbon::setTestNow();
    }

    public function test_export_dispatches_job_to_the_exports_queue(): void
    {
        Queue::fake();

        $tenant = $this->makeTenant('exp-co');
        $user = $this->makeUser($tenant, 'a@exp.test');

        $response = $this->actingAs($user, 'tenant')
            ->post("/app/{$tenant->slug}/reports/export", [
                'report_type' => 'revenue',
                'format' => ReportExport::FORMAT_PDF,
            ]);

        $response->assertRedirect();

        // A pending export row exists, and the job is queued on 'exports'.
        app()->instance('current_tenant', $tenant);
        $export = ReportExport::where('report_type', 'revenue')->firstOrFail();
        $this->assertSame(ReportExport::STATUS_PENDING, $export->status);

        Queue::assertPushedOn('exports', GenerateReportExportJob::class);
    }
}
