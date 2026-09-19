<?php

namespace Tests\Feature;

use App\Jobs\GenerateReportExportJob;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\DTOs\ExpenseDTO;
use App\Modules\Finance\Events\ExpenseRecorded;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\ExpenseCategoryService;
use App\Modules\Finance\Services\ExpenseReportService;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Services\ExportService;
use App\Modules\Reporting\Services\ReportingService;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Carbon\Carbon;
use Database\Seeders\TenantRolesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Client feedback #9 — Finance → Expenses: GST-inclusive amounts (1/11 auto,
 * overridable, or none), append-only ledger (business-level entries, no
 * customer; edit = reversal + new; void = reversal), customer balances
 * untouched, permission matrix, private receipts, default categories, reports
 * (Australian FY), profit per vehicle, exports, tenant isolation.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class ExpensesTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $limits = []): Tenant
    {
        $plan = Plan::create([
            'name' => 'Plan '.$slug, 'slug' => 'plan-'.$slug.'-'.uniqid(),
            'price_monthly' => 5000, 'price_annual' => 50000, 'is_active' => true, 'is_free' => false,
            'trial_days' => 14, 'modules' => Plan::MODULE_KEYS, 'limits' => $limits, 'sort_order' => 0,
        ]);
        $tenant = Tenant::create(['name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE, 'plan_id' => $plan->id]);

        app()->instance('current_tenant', $tenant);
        Subscription::create([
            'tenant_id' => $tenant->id, 'plan_id' => $plan->id, 'status' => Subscription::STATUS_ACTIVE,
            'billing_cycle' => Subscription::BILLING_MONTHLY,
            'current_period_start' => now(), 'current_period_end' => now()->addMonth(),
        ]);
        app(ExpenseCategoryService::class)->ensureDefaults($tenant);

        return $tenant;
    }

    private function makeUser(Tenant $tenant, string $role): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => ucfirst($role), 'email' => $role.'-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => $role,
        ]);
    }

    private function category(Tenant $tenant, string $key = 'daily'): ExpenseCategory
    {
        app()->instance('current_tenant', $tenant);

        return ExpenseCategory::where('system_key', $key)->firstOrFail();
    }

    private function payload(Tenant $tenant, array $overrides = []): array
    {
        return array_merge([
            'expense_category_id' => $this->category($tenant)->id,
            'expense_date' => today()->toDateString(),
            'description' => 'Fuel',
            'supplier' => 'BP Canning Vale',
            'amount_total' => 11000, // $110.00 incl. GST
            'includes_gst' => 1,
            'payment_method' => Expense::METHOD_CARD,
        ], $overrides);
    }

    private function url(Tenant $tenant, string $suffix = ''): string
    {
        return "/app/{$tenant->slug}/expenses{$suffix}";
    }

    private function ledgerFor(Tenant $tenant, Expense $expense)
    {
        app()->instance('current_tenant', $tenant);

        return LedgerEntry::query()
            ->where('reference_type', 'expense')
            ->where('reference_id', $expense->id)
            ->orderBy('id')
            ->get();
    }

    private function only(Tenant $tenant): Expense
    {
        app()->instance('current_tenant', $tenant);

        return Expense::query()->sole();
    }

    // ── Recording + GST ─────────────────────────────────────────────────────

    public function test_recording_stores_gst_inclusive_amounts_and_one_business_ledger_entry(): void
    {
        Event::fake([ExpenseRecorded::class]);
        $t = $this->makeTenant('exp-a');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t))->assertSessionHasNoErrors();

        $e = $this->only($t);
        $this->assertSame(11000, $e->amount_total);
        $this->assertSame(1000, $e->gst_amount);      // 1/11
        $this->assertSame(10000, $e->amount_ex_gst);
        $this->assertSame((int) $admin->id, (int) $e->created_by);

        $ledger = $this->ledgerFor($t, $e);
        $this->assertCount(1, $ledger);
        $this->assertNull($ledger[0]->customer_id);
        $this->assertSame(LedgerEntry::TYPE_EXPENSE, $ledger[0]->type);
        $this->assertSame(11000, $ledger[0]->amount);

        Event::assertDispatched(ExpenseRecorded::class);
    }

    public function test_gst_override_no_gst_and_gst_above_total(): void
    {
        $this->assertSame(909, ExpenseDTO::standardGst(9999)); // rounds to the nearest cent

        $t = $this->makeTenant('exp-b');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['description' => 'Mixed', 'gst_amount' => 450]));
        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['description' => 'Rego', 'includes_gst' => 0, 'gst_amount' => 500]));
        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['description' => 'Bad', 'gst_amount' => 20000]))
            ->assertSessionHasErrors('gst_amount');

        app()->instance('current_tenant', $t);
        $mixed = Expense::where('description', 'Mixed')->sole();
        $this->assertSame([450, 10550], [$mixed->gst_amount, $mixed->amount_ex_gst]);
        $rego = Expense::where('description', 'Rego')->sole();
        $this->assertSame([0, 11000, false], [$rego->gst_amount, $rego->amount_ex_gst, $rego->includes_gst]);
        $this->assertSame(0, Expense::where('description', 'Bad')->count());
    }

    // ── Ledger: edit / void ─────────────────────────────────────────────────

    public function test_editing_the_total_appends_a_reversal_and_a_new_entry_and_never_mutates(): void
    {
        $t = $this->makeTenant('exp-c');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);
        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t));
        $e = $this->only($t);
        $original = $this->ledgerFor($t, $e)->first();

        // Description-only edit → no ledger rows.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['description' => 'Fuel (ute)']))
            ->assertSessionHasNoErrors();
        $this->assertCount(1, $this->ledgerFor($t, $e));

        // Total change → −old, +new.
        $this->actingAs($admin, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['amount_total' => 22000]))
            ->assertSessionHasNoErrors();

        $ledger = $this->ledgerFor($t, $e);
        $this->assertSame([11000, -11000, 22000], $ledger->pluck('amount')->all());
        $this->assertSame(22000, (int) $ledger->sum('amount'));
        $this->assertEquals($original->getAttributes(), $ledger->first()->getAttributes()); // untouched

        $fresh = $e->fresh();
        $this->assertSame([22000, 2000, 20000], [$fresh->amount_total, $fresh->gst_amount, $fresh->amount_ex_gst]);
    }

    public function test_voiding_reverses_the_ledger_drops_out_of_totals_and_locks_the_expense(): void
    {
        $t = $this->makeTenant('exp-d');
        $accounts = $this->makeUser($t, TenantUser::ROLE_ACCOUNTS);
        $this->actingAs($accounts, 'tenant')->post($this->url($t), $this->payload($t));
        $e = $this->only($t);

        $this->actingAs($accounts, 'tenant')->post($this->url($t, "/{$e->id}/void"), [])->assertSessionHasErrors('reason');
        $this->actingAs($accounts, 'tenant')->post($this->url($t, "/{$e->id}/void"), ['reason' => 'Duplicate'])
            ->assertSessionHasNoErrors();

        $this->assertSame([11000, -11000], $this->ledgerFor($t, $e)->pluck('amount')->all());

        $fresh = $e->fresh();
        $this->assertNotNull($fresh->voided_at);
        $this->assertSame('Duplicate', $fresh->void_reason);

        app()->instance('current_tenant', $t);
        $summary = app(ExpenseReportService::class)->summary(today()->startOfMonth(), today()->endOfMonth());
        $this->assertSame(0, $summary['totals']['total']);

        // Locked: no edit, no second void.
        $this->actingAs($accounts, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t))->assertForbidden();
        $this->actingAs($accounts, 'tenant')->post($this->url($t, "/{$e->id}/void"), ['reason' => 'Again'])->assertForbidden();
    }

    public function test_expense_entries_never_touch_customer_balances(): void
    {
        $t = $this->makeTenant('exp-e');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        app()->instance('current_tenant', $t);
        $customer = Customer::create([
            'name' => 'Renter', 'email' => 'r-'.uniqid().'@test.au', 'phone' => '0400',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);
        app(LedgerService::class)->append($t->id, $customer->id, LedgerEntry::TYPE_RENTAL_CHARGE, 5000, 'Rent');

        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['amount_total' => 99900]));

        app()->instance('current_tenant', $t);
        $this->assertSame(5000, app(LedgerService::class)->getBalance($customer->id));
        $this->assertTrue($customer->hasOutstandingBalance());

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/customers")
            ->assertInertia(fn ($page) => $page->where('customers.data.0.outstanding_balance', 5000));
    }

    public function test_database_refuses_customerless_entries_for_non_expense_types(): void
    {
        $t = $this->makeTenant('exp-f');
        app()->instance('current_tenant', $t);

        $this->expectException(QueryException::class);
        LedgerEntry::create(['tenant_id' => $t->id, 'customer_id' => null, 'type' => LedgerEntry::TYPE_PAYMENT, 'amount' => -100, 'description' => 'x']);
    }

    public function test_append_business_rejects_customer_types(): void
    {
        $t = $this->makeTenant('exp-g');
        $this->expectException(\InvalidArgumentException::class);
        app(LedgerService::class)->appendBusiness($t->id, LedgerEntry::TYPE_PAYMENT, 100, 'x');
    }

    // ── Permissions ─────────────────────────────────────────────────────────

    public function test_staff_can_record_and_edit_own_same_day_only_never_void_or_manage_categories(): void
    {
        $t = $this->makeTenant('exp-h');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);
        $other = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')->post($this->url($t), $this->payload($t))->assertSessionHasNoErrors();
        $e = $this->only($t);

        $this->actingAs($staff, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['description' => 'Fixed typo']))
            ->assertSessionHasNoErrors();
        $this->actingAs($other, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t))->assertForbidden();
        $this->actingAs($staff, 'tenant')->post($this->url($t, "/{$e->id}/void"), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($staff, 'tenant')->post("/app/{$t->slug}/expense-categories", ['name' => 'Fuel'])->assertForbidden();

        $this->travel(1)->days();
        $this->actingAs($staff, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['expense_date' => today()->subDay()->toDateString()]))
            ->assertForbidden();

        $this->actingAs($staff, 'tenant')->get($this->url($t))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('canManage', false)
                ->where('expenses.data.0.can_edit', false)
                ->where('expenses.data.0.can_void', false));
    }

    public function test_admin_and_accounts_manage_everything(): void
    {
        $t = $this->makeTenant('exp-i');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);
        $accounts = $this->makeUser($t, TenantUser::ROLE_ACCOUNTS);

        $this->actingAs($staff, 'tenant')->post($this->url($t), $this->payload($t));
        $e = $this->only($t);

        $this->travel(3)->days();
        $this->actingAs($accounts, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['expense_date' => today()->subDays(3)->toDateString(), 'description' => 'Corrected']))
            ->assertSessionHasNoErrors();
        $this->actingAs($accounts, 'tenant')->post("/app/{$t->slug}/expense-categories", ['name' => 'Fuel'])->assertSessionHasNoErrors();
        $this->actingAs($accounts, 'tenant')->post("/app/{$t->slug}/expense-categories", ['name' => 'fuel'])->assertSessionHasErrors('name');

        app()->instance('current_tenant', $t);
        $utilities = $this->category($t, 'utilities');
        $this->actingAs($accounts, 'tenant')->put("/app/{$t->slug}/expense-categories/{$utilities->id}", ['name' => 'Power & water', 'is_hidden' => true])
            ->assertSessionHasNoErrors();
        $fresh = $utilities->fresh();
        $this->assertSame(['Power & water', true, 'utilities'], [$fresh->name, $fresh->is_hidden, $fresh->system_key]);
    }

    // ── Receipts ────────────────────────────────────────────────────────────

    public function test_receipt_is_private_signed_and_replaced_files_are_deleted(): void
    {
        $disk = Storage::fake(config('filesystems.default'));
        $t = $this->makeTenant('exp-j');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['receipt' => UploadedFile::fake()->image('r.jpg')]))
            ->assertSessionHasNoErrors();
        $e = $this->only($t);
        $path = $e->fresh()->receipt_path;

        $this->assertMatchesRegularExpression("#^tenants/{$t->id}/expenses/{$e->id}/receipt-[0-9a-f-]{36}\.jpg$#", $path);
        $disk->assertExists($path);
        $this->assertArrayNotHasKey('receipt_path', $e->fresh()->toArray());
        $this->assertTrue($e->fresh()->has_receipt);

        $location = $this->actingAs($admin, 'tenant')->get($this->url($t, "/{$e->id}/receipt"))->assertRedirect()->headers->get('Location');
        $this->assertStringContainsString($path, $location);

        $this->actingAs($admin, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['receipt' => UploadedFile::fake()->create('r.pdf', 50, 'application/pdf')]))
            ->assertSessionHasNoErrors();
        $disk->assertMissing($path);
        $disk->assertExists($e->fresh()->receipt_path);

        $this->actingAs($admin, 'tenant')->put($this->url($t, "/{$e->id}"), $this->payload($t, ['remove_receipt' => 1]));
        $this->assertNull($e->fresh()->receipt_path);
        $this->assertCount(0, $disk->allFiles("tenants/{$t->id}/expenses"));
    }

    public function test_receipt_respects_the_plan_file_size_limit(): void
    {
        $disk = Storage::fake(config('filesystems.default'));
        $t = $this->makeTenant('exp-k', ['max_file_size_mb' => 1]);
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        $this->actingAs($admin, 'tenant')
            ->post($this->url($t), $this->payload($t, ['receipt' => UploadedFile::fake()->create('big.pdf', 2048, 'application/pdf')]))
            ->assertSessionHasErrors('receipt');

        app()->instance('current_tenant', $t);
        $this->assertSame(0, Expense::count());
        $this->assertCount(0, $disk->allFiles());
    }

    // ── Categories ──────────────────────────────────────────────────────────

    public function test_new_tenant_registration_gets_the_three_default_categories(): void
    {
        $this->seed(TenantRolesSeeder::class);
        Plan::create([
            'name' => 'Free', 'slug' => 'free-'.Str::random(6), 'price_monthly' => 0, 'price_annual' => 0,
            'is_active' => true, 'is_free' => true, 'trial_days' => 14, 'modules' => [], 'limits' => [], 'sort_order' => 1,
        ]);
        $company = 'Expense Co '.Str::random(5);

        $this->post('/register', [
            'company_name' => $company, 'email' => 'owner@example.com',
            'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertRedirect();

        app()->instance('current_tenant', Tenant::where('slug', Str::slug($company))->firstOrFail());
        $this->assertEqualsCanonicalizing(['daily', 'government', 'utilities'], ExpenseCategory::pluck('system_key')->all());
        $this->assertSame(['Daily expenses', 'Government fees', 'Utilities'], ExpenseCategory::query()->ordered()->pluck('name')->all());
    }

    public function test_ensure_defaults_is_idempotent(): void
    {
        $t = $this->makeTenant('exp-l');
        app(ExpenseCategoryService::class)->ensureDefaults($t);
        app(ExpenseCategoryService::class)->ensureDefaults($t);

        app()->instance('current_tenant', $t);
        $this->assertSame(3, ExpenseCategory::count());
    }

    // ── Reports ─────────────────────────────────────────────────────────────

    public function test_summary_respects_the_australian_financial_year(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 8, 15, 10, 0, 0, 'Australia/Sydney'));
        $t = $this->makeTenant('exp-m');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);

        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['expense_date' => '2026-06-30', 'amount_total' => 1100]));
        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['expense_date' => '2026-07-01', 'amount_total' => 2200]));
        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, [
            'expense_date' => '2026-08-01', 'amount_total' => 3300,
            'expense_category_id' => $this->category($t, 'government')->id, 'includes_gst' => 0,
        ]));

        $this->actingAs($admin, 'tenant')->get($this->url($t))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.fy_label', 'FY26/27')
                ->where('summary.fy.total', 5500)
                ->where('summary.fy.gst', 200)
                ->where('summary.month.total', 3300));

        app()->instance('current_tenant', $t);
        $fy = app(ReportingService::class)->australianFY();
        $report = app(ExpenseReportService::class)->summary($fy['from'], $fy['to']);
        $this->assertSame(['2026-07', '2026-08'], array_column($report['by_month'], 'month'));
        $this->assertSame(['Government fees', 'Daily expenses'], array_column($report['by_category'], 'category'));

        Carbon::setTestNow();
    }

    public function test_profit_per_vehicle_subtracts_expenses_and_workshop_costs(): void
    {
        $t = $this->makeTenant('exp-n');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);
        app()->instance('current_tenant', $t);
        $vehicle = Vehicle::create([
            'registration_number' => 'PROF1', 'make' => 'Toyota', 'model' => 'Yaris', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 5000,
        ]);
        $mechanic = Mechanic::create(['name' => 'M', 'email' => 'm-'.uniqid().'@x.test', 'password' => 'secret123', 'is_active' => true]);
        ServiceLog::create([
            'vehicle_id' => $vehicle->id, 'mechanic_id' => $mechanic->id, 'status' => ServiceLog::STATUS_COMPLETED,
            'title' => 'Service', 'labour_cost' => 5000, 'total_cost' => 5000, 'started_at' => now(), 'completed_at' => now(),
        ]);

        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t, ['vehicle_id' => $vehicle->id, 'amount_total' => 10000]));

        app()->instance('current_tenant', $t);
        $rows = app(ReportingService::class)->revenueByVehicle(today()->startOfMonth(), today()->endOfDay());
        $row = collect($rows)->firstWhere('vehicle', 'PROF1 Toyota Yaris');

        $this->assertNotNull($row);
        $this->assertSame([0, 10000, 5000, -15000], [$row['revenue'], $row['expenses'], $row['maintenance'], $row['profit']]);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/fleet/{$vehicle->id}")
            ->assertInertia(fn ($page) => $page
                ->has('vehicleExpenses', 1)
                ->where('vehicleExpensesFy', 10000));
    }

    public function test_expenses_report_page_export_and_excel(): void
    {
        Queue::fake();
        $disk = Storage::fake(config('filesystems.default'));
        $t = $this->makeTenant('exp-o');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);
        $this->actingAs($admin, 'tenant')->post($this->url($t), $this->payload($t));

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/reports/expenses")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Reporting/Expenses')->where('report.totals.total', 11000));

        $this->actingAs($admin, 'tenant')->post("/app/{$t->slug}/reports/export", [
            'report_type' => 'expenses', 'format' => ReportExport::FORMAT_EXCEL,
        ])->assertSessionHasNoErrors();
        Queue::assertPushedOn('exports', GenerateReportExportJob::class);

        app()->instance('current_tenant', $t);
        $data = app(ReportingService::class)->expenses(today()->startOfMonth(), today()->endOfMonth());
        $xlsx = app(ExportService::class)->exportExcel('expenses', $data, 'Expenses');
        $pdf = app(ExportService::class)->exportPdf('expenses', $data, 'Expenses');
        $disk->assertExists($xlsx);
        $disk->assertExists($pdf);
        $this->assertStringStartsWith('%PDF', $disk->get($pdf));
    }

    // ── Isolation ───────────────────────────────────────────────────────────

    public function test_tenants_are_isolated(): void
    {
        $a = $this->makeTenant('exp-p1');
        $adminA = $this->makeUser($a, TenantUser::ROLE_ADMIN);
        $b = $this->makeTenant('exp-p2');
        $adminB = $this->makeUser($b, TenantUser::ROLE_ADMIN);

        $this->actingAs($adminA, 'tenant')->post($this->url($a), $this->payload($a));
        $e = $this->only($a);

        $this->actingAs($adminB, 'tenant')->get($this->url($b))->assertInertia(fn ($page) => $page->has('expenses.data', 0));
        $this->actingAs($adminB, 'tenant')->put($this->url($b, "/{$e->id}"), $this->payload($b))->assertNotFound();
        $this->actingAs($adminB, 'tenant')->post($this->url($b, "/{$e->id}/void"), ['reason' => 'x'])->assertNotFound();
        $this->actingAs($adminB, 'tenant')->get($this->url($b, "/{$e->id}/receipt"))->assertNotFound();

        // B can't file an expense under A's category.
        $this->actingAs($adminB, 'tenant')
            ->post($this->url($b), $this->payload($b, ['expense_category_id' => $this->category($a)->id]))
            ->assertSessionHasErrors('expense_category_id');
    }
}
