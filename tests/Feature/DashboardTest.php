<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\CRM\Models\Lead;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\ExpenseCategory;
use App\Modules\Finance\Services\ExpenseCategoryService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use App\Modules\Reporting\Services\DashboardService;
use App\Modules\Reporting\Services\ReportCacheService;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The tenant dashboard: what needs attention today, what the fleet is doing,
 * and what the week holds.
 *
 * Two rules carry the most weight here:
 *  - a staff member's payload must not CONTAIN money, not merely hide it;
 *  - one company's dashboard must never count another company's rows.
 */
class DashboardTest extends TestCase
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
        Cache::flush(); // never read another test's cached dashboard

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

    private function vehicle(Tenant $tenant, string $rego, array $attrs = []): Vehicle
    {
        app()->instance('current_tenant', $tenant);

        $vehicle = new Vehicle(array_merge([
            'registration_number' => $rego, 'make' => 'Toyota', 'model' => 'Camry', 'year' => 2023,
            'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 9000,
        ], $attrs));
        $vehicle->save();

        return $vehicle;
    }

    private function customer(Tenant $tenant): Customer
    {
        app()->instance('current_tenant', $tenant);

        return Customer::create([
            'name' => 'Renter '.uniqid(), 'email' => 'r-'.uniqid().'@test.au', 'phone' => '0400000000',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);
    }

    private function overdueInvoice(Tenant $tenant, int $daysLate, int $total = 50000): Invoice
    {
        app()->instance('current_tenant', $tenant);

        return Invoice::create([
            'customer_id' => $this->customer($tenant)->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => today()->subDays($daysLate + 7),
            'due_date' => today()->subDays($daysLate),
            'billing_period_start' => today()->subDays($daysLate + 30),
            'billing_period_end' => today()->subDays($daysLate),
            'subtotal' => $total, 'total' => $total, 'paid_amount' => 0,
        ]);
    }

    private function agreement(Tenant $tenant, array $attrs = []): Agreement
    {
        app()->instance('current_tenant', $tenant);

        return Agreement::create(array_merge([
            'customer_id' => $this->customer($tenant)->id,
            'vehicle_id' => $this->vehicle($tenant, 'AG'.random_int(100, 999))->id,
            'type' => 'private',
            'status' => Agreement::STATUS_ACTIVE,
            'billing_cycle' => 'weekly',
            'rate' => 90000,
            'bond_amount' => 50000,
            'start_date' => today(),
            'version' => 1,
        ], $attrs));
    }

    private function dashboard(Tenant $tenant): DashboardService
    {
        app()->instance('current_tenant', $tenant);

        return app(DashboardService::class);
    }

    // ── Needs attention ────────────────────────────────────────────────────

    public function test_a_brand_new_company_sees_an_empty_attention_list(): void
    {
        $t = $this->makeTenant('dash-new');

        $attention = $this->dashboard($t)->needsAttention();

        $this->assertSame([], $attention['groups']);
        $this->assertSame(0, $attention['total']);
        $this->assertSame(0, $attention['urgent']);
    }

    public function test_overdue_invoices_are_listed_most_overdue_first_and_flagged_urgent(): void
    {
        $t = $this->makeTenant('dash-overdue');
        $this->overdueInvoice($t, daysLate: 3, total: 20000);
        $this->overdueInvoice($t, daysLate: 40, total: 150000);
        // Not yet due — must not appear.
        app()->instance('current_tenant', $t);
        Invoice::create([
            'customer_id' => $this->customer($t)->id, 'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT, 'issue_date' => today(), 'due_date' => today()->addDays(5),
            'billing_period_start' => today(), 'billing_period_end' => today()->addMonth(),
            'subtotal' => 10000, 'total' => 10000, 'paid_amount' => 0,
        ]);

        $group = collect($this->dashboard($t)->needsAttention()['groups'])->firstWhere('key', 'overdue_invoices');

        $this->assertSame(2, $group['count']);
        $this->assertSame(DashboardService::SEVERITY_URGENT, $group['severity']);
        $this->assertSame(40, $group['items'][0]['detail_value']); // oldest first
        $this->assertSame(150000, $group['items'][0]['amount']);
        $this->assertStringStartsWith('invoices/', $group['items'][0]['url']);
    }

    public function test_a_paid_invoice_drops_off_the_list(): void
    {
        $t = $this->makeTenant('dash-paid');
        $invoice = $this->overdueInvoice($t, daysLate: 10);

        $this->assertSame(1, collect($this->dashboard($t)->needsAttention()['groups'])
            ->firstWhere('key', 'overdue_invoices')['count']);

        $invoice->update(['status' => Invoice::STATUS_PAID, 'paid_amount' => $invoice->total]);

        $this->assertNull(collect($this->dashboard($t)->needsAttention()['groups'])
            ->firstWhere('key', 'overdue_invoices'));
    }

    public function test_fleet_expiries_report_the_most_urgent_date_per_vehicle(): void
    {
        $t = $this->makeTenant('dash-expiry');
        // Registration already past AND service due soon — overdue must win.
        $this->vehicle($t, 'EXP001', [
            'registration_expiry' => today()->subDays(3)->toDateString(),
            'next_service_due' => today()->addDays(10)->toDateString(),
        ]);
        $this->vehicle($t, 'EXP002', ['insurance_expiry' => today()->addDays(5)->toDateString()]);
        $this->vehicle($t, 'EXP003'); // nothing recorded — never appears

        $group = collect($this->dashboard($t)->needsAttention()['groups'])->firstWhere('key', 'fleet_expiries');

        $this->assertSame(2, $group['count']);
        $this->assertSame(DashboardService::SEVERITY_URGENT, $group['severity']);

        $first = $group['items'][0];
        $this->assertSame('EXP001', $first['label']);
        $this->assertSame('registration', $first['detail_key']);
        $this->assertTrue($first['overdue']);
    }

    public function test_service_due_by_km_counts_as_an_expiry(): void
    {
        $t = $this->makeTenant('dash-km');
        $vehicle = $this->vehicle($t, 'KM001');
        // Not fillable: the app derives these (RecordOdometerReadingAction).
        $vehicle->forceFill(['current_odometer' => 99500, 'next_service_km' => 100000])->save();

        $group = collect($this->dashboard($t)->needsAttention()['groups'])->firstWhere('key', 'fleet_expiries');

        $this->assertSame(1, $group['count']);
        $this->assertSame('service_km', $group['items'][0]['detail_key']);
        $this->assertSame('100,000 km', $group['items'][0]['detail_value']);
    }

    public function test_agreements_ending_soon_leads_and_off_road_vehicles(): void
    {
        $t = $this->makeTenant('dash-mixed');

        $this->agreement($t, ['end_date' => today()->addDays(5)]);
        $this->agreement($t, ['end_date' => today()->addDays(90)]);   // too far out
        $this->agreement($t, ['end_date' => null]);                    // open-ended

        app()->instance('current_tenant', $t);
        Lead::create(['name' => 'Waiting Lead', 'phone' => '0400 111 222', 'email' => 'lead@test.au',
            'status' => Lead::STATUS_NEW, 'submitted_at' => now(), 'token' => bin2hex(random_bytes(16))]);
        Lead::create(['name' => 'Never Submitted', 'phone' => '0400 333 444', 'email' => 'l2@test.au',
            'status' => Lead::STATUS_NEW, 'token' => bin2hex(random_bytes(16))]);

        $this->vehicle($t, 'OFF001', ['status' => Vehicle::STATUS_ACCIDENT]);
        $this->vehicle($t, 'OFF002', ['status' => Vehicle::STATUS_SUSPENDED]);
        $this->vehicle($t, 'OK001', ['status' => Vehicle::STATUS_MAINTENANCE]); // not "off road"

        $groups = collect($this->dashboard($t)->needsAttention()['groups'])->keyBy('key');

        $this->assertSame(1, $groups['agreements_ending']['count']);
        $this->assertSame(1, $groups['unconverted_leads']['count']);
        $this->assertSame('Waiting Lead', $groups['unconverted_leads']['items'][0]['label']);
        $this->assertSame(2, $groups['vehicles_off_road']['count']);
    }

    public function test_long_lists_are_capped_but_still_counted(): void
    {
        $t = $this->makeTenant('dash-cap');

        foreach (range(1, 8) as $i) {
            $this->overdueInvoice($t, daysLate: $i);
        }

        $group = collect($this->dashboard($t)->needsAttention()['groups'])->firstWhere('key', 'overdue_invoices');

        $this->assertSame(8, $group['count'], 'the count is the truth');
        $this->assertCount(DashboardService::ATTENTION_LIMIT, $group['items'], 'the list is capped');
    }

    // ── Fleet snapshot ─────────────────────────────────────────────────────

    public function test_the_fleet_snapshot_covers_every_status_even_at_zero(): void
    {
        $t = $this->makeTenant('dash-fleet');
        $this->vehicle($t, 'F1', ['status' => Vehicle::STATUS_RENTED]);
        $this->vehicle($t, 'F2', ['status' => Vehicle::STATUS_RENTED]);
        $this->vehicle($t, 'F3', ['status' => Vehicle::STATUS_AVAILABLE]);
        $this->vehicle($t, 'F4', ['status' => Vehicle::STATUS_MAINTENANCE]);

        $snapshot = $this->dashboard($t)->fleetSnapshot();

        $this->assertCount(count(Vehicle::STATUSES), $snapshot['statuses']);
        $this->assertSame(4, $snapshot['total']);
        $this->assertSame(50.0, $snapshot['utilisation']); // 2 of 4 on the road

        $byStatus = collect($snapshot['statuses'])->keyBy('status');
        $this->assertSame(2, $byStatus['rented']['count']);
        $this->assertSame(50.0, $byStatus['rented']['percentage']);
        $this->assertSame(0, $byStatus['accident']['count']);
    }

    public function test_an_empty_fleet_does_not_divide_by_zero(): void
    {
        $t = $this->makeTenant('dash-empty-fleet');

        $snapshot = $this->dashboard($t)->fleetSnapshot();

        $this->assertSame(0, $snapshot['total']);
        $this->assertSame(0.0, $snapshot['utilisation']);
        $this->assertSame(0, collect($snapshot['statuses'])->sum('count'));
    }

    // ── The week ahead ─────────────────────────────────────────────────────

    public function test_the_week_block_counts_what_starts_ends_and_is_booked(): void
    {
        $t = $this->makeTenant('dash-week');

        $this->agreement($t, ['start_date' => today(), 'end_date' => today()->addDays(3)]);
        $this->agreement($t, ['start_date' => today()->addDays(2), 'end_date' => today()->addMonths(3)]);
        $this->agreement($t, ['start_date' => today()->subMonth(), 'end_date' => today()]);
        $this->agreement($t, ['start_date' => today()->addDays(30), 'end_date' => today()->addDays(60)]); // outside

        app()->instance('current_tenant', $t);
        $mechanic = Mechanic::create([
            'name' => 'Sam Wrench', 'email' => 'sam-'.uniqid().'@wk.test',
            'phone' => '0400000000', 'pin' => '1234', 'password' => 'secret123', 'is_active' => true,
        ]);
        ServiceLog::create([
            'vehicle_id' => $this->vehicle($t, 'WK001')->id,
            'mechanic_id' => $mechanic->id,
            'status' => ServiceLog::STATUS_PENDING,
            'title' => 'Brake pads',
            'description' => 'Brake pads',
        ]);

        $week = $this->dashboard($t)->thisWeek();

        $this->assertSame(2, $week['starting']);       // today + in 2 days
        $this->assertSame(2, $week['ending']);         // in 3 days + today
        $this->assertSame(1, $week['starting_today']);
        $this->assertSame(1, $week['ending_today']);
        $this->assertSame(1, $week['services_booked']);
    }

    // ── Money ──────────────────────────────────────────────────────────────

    /** Cash received, which is what the money block counts as revenue. */
    private function payment(Tenant $tenant, int $amount, string $paidAt): Payment
    {
        app()->instance('current_tenant', $tenant);

        $invoice = Invoice::create([
            'customer_id' => $this->customer($tenant)->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_PAID,
            'issue_date' => $paidAt,
            'due_date' => $paidAt,
            'billing_period_start' => $paidAt,
            'billing_period_end' => $paidAt,
            'subtotal' => $amount, 'total' => $amount, 'paid_amount' => $amount,
        ]);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $invoice->customer_id,
            'amount' => $amount,
            'method' => Payment::METHOD_CASH,
            'paid_at' => $paidAt,
        ]);
    }

    public function test_revenue_compares_this_month_with_last(): void
    {
        $t = $this->makeTenant('dash-money');

        $this->payment($t, 200000, now()->startOfMonth()->addDays(2)->toDateTimeString());
        $this->payment($t, 50000, now()->startOfMonth()->addDays(5)->toDateTimeString());
        $this->payment($t, 100000, now()->startOfMonth()->subMonth()->addDays(3)->toDateTimeString());

        $money = $this->dashboard($t)->money();

        $this->assertSame(250000, $money['revenue_this_month']);
        $this->assertSame(100000, $money['revenue_last_month']);
        $this->assertSame(150.0, $money['revenue_change_pct']); // 100k → 250k
    }

    public function test_a_first_month_has_no_percentage_instead_of_infinity(): void
    {
        $t = $this->makeTenant('dash-money-first');
        $this->payment($t, 80000, now()->startOfMonth()->addDay()->toDateTimeString());

        $money = $this->dashboard($t)->money();

        $this->assertSame(80000, $money['revenue_this_month']);
        $this->assertSame(0, $money['revenue_last_month']);
        $this->assertNull($money['revenue_change_pct'], 'dividing by a zero month must not produce a figure');
    }

    public function test_receivables_are_aged_into_three_bands(): void
    {
        $t = $this->makeTenant('dash-ageing');

        // Not yet due.
        app()->instance('current_tenant', $t);
        Invoice::create([
            'customer_id' => $this->customer($t)->id, 'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT, 'issue_date' => today(), 'due_date' => today()->addDays(5),
            'billing_period_start' => today(), 'billing_period_end' => today()->addMonth(),
            'subtotal' => 10000, 'total' => 10000, 'paid_amount' => 0,
        ]);
        $this->overdueInvoice($t, daysLate: 10, total: 20000);   // up to 30 days
        $this->overdueInvoice($t, daysLate: 45, total: 30000);   // over 30 days

        $r = $this->dashboard($t)->money()['receivables'];

        $this->assertSame(10000, $r['current']);
        $this->assertSame(20000, $r['late_30']);
        $this->assertSame(30000, $r['late_over_30']);
        $this->assertSame(60000, $r['total']);
    }

    public function test_receivables_count_only_what_is_still_owed(): void
    {
        $t = $this->makeTenant('dash-partial');

        $invoice = $this->overdueInvoice($t, daysLate: 5, total: 100000);
        $invoice->update(['paid_amount' => 70000]); // part-paid

        $this->assertSame(30000, $this->dashboard($t)->money()['receivables']['total']);

        $invoice->update(['status' => Invoice::STATUS_CANCELLED]);
        $this->assertSame(0, $this->dashboard($t)->money()['receivables']['total']);
    }

    public function test_the_trend_always_returns_six_months_including_empty_ones(): void
    {
        $t = $this->makeTenant('dash-trend');
        $this->payment($t, 90000, now()->startOfMonth()->subMonths(2)->addDay()->toDateTimeString());

        $trend = $this->dashboard($t)->money()['trend'];

        $this->assertCount(DashboardService::TREND_MONTHS, $trend);
        $this->assertSame(now()->startOfMonth()->format('Y-m'), end($trend)['month'], 'ends on this month');
        $this->assertSame(90000, collect($trend)->firstWhere('month', now()->subMonths(2)->format('Y-m'))['revenue']);
        $this->assertSame(0, collect($trend)->firstWhere('month', now()->subMonth()->format('Y-m'))['revenue']);
    }

    public function test_net_subtracts_this_months_expenses(): void
    {
        $t = $this->makeTenant('dash-net');
        $this->payment($t, 100000, now()->startOfMonth()->addDay()->toDateTimeString());

        app()->instance('current_tenant', $t);
        // ensureDefaults() returns void — it seeds the three system categories.
        app(ExpenseCategoryService::class)->ensureDefaults($t);
        $category = ExpenseCategory::query()->firstOrFail();

        Expense::create([
            'expense_category_id' => $category->id,
            'expense_date' => today(),
            'description' => 'Fuel',
            'amount_total' => 22000,
            'gst_amount' => 2000,
            'amount_ex_gst' => 20000,
            'includes_gst' => true,
            'payment_method' => 'card',
        ]);

        $money = $this->dashboard($t)->money();

        $this->assertSame(22000, $money['expenses_this_month']);
        $this->assertSame(78000, $money['net_this_month']);
    }

    public function test_the_money_block_is_absent_for_staff_not_merely_zeroed(): void
    {
        $t = $this->makeTenant('dash-money-role');
        $this->payment($t, 100000, now()->startOfMonth()->addDay()->toDateTimeString());
        $staff = $this->user($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')->get("/app/{$t->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('operational.money', null));

        Cache::flush();
        $accounts = $this->user($t, TenantUser::ROLE_ACCOUNTS);

        $this->actingAs($accounts, 'tenant')->get("/app/{$t->slug}/dashboard")
            ->assertInertia(fn ($page) => $page
                ->where('operational.money.revenue_this_month', 100000)
                ->has('operational.money.receivables')
                ->has('operational.money.trend', DashboardService::TREND_MONTHS));
    }

    // ── Role and isolation ─────────────────────────────────────────────────

    public function test_staff_are_never_sent_money(): void
    {
        $t = $this->makeTenant('dash-role');
        $this->overdueInvoice($t, daysLate: 10, total: 123400);
        $staff = $this->user($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')->get("/app/{$t->slug}/dashboard")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Tenant/Dashboard')
                ->where('seesMoney', false)
                // The payload itself must not carry the figures.
                ->missing('summary.outstanding_balance')
                ->where('operational.attention.groups', fn ($groups) => collect($groups)
                    ->doesntContain(fn ($g) => $g['key'] === 'overdue_invoices')));
    }

    public function test_admins_and_accounts_see_money(): void
    {
        $t = $this->makeTenant('dash-role2');
        $this->overdueInvoice($t, daysLate: 10, total: 123400);

        foreach ([TenantUser::ROLE_ADMIN, TenantUser::ROLE_ACCOUNTS] as $role) {
            Cache::flush();
            $user = $this->user($t, $role);

            $this->actingAs($user, 'tenant')->get("/app/{$t->slug}/dashboard")
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->where('seesMoney', true)
                    ->has('summary.outstanding_balance')
                    ->where('operational.attention.groups', fn ($groups) => collect($groups)
                        ->contains(fn ($g) => $g['key'] === 'overdue_invoices')));
        }
    }

    public function test_one_companys_dashboard_never_counts_anothers_rows(): void
    {
        $a = $this->makeTenant('dash-a');
        $this->overdueInvoice($a, daysLate: 10);
        $this->vehicle($a, 'AAA111', ['status' => Vehicle::STATUS_ACCIDENT]);

        $b = $this->makeTenant('dash-b');
        $this->vehicle($b, 'BBB222', ['status' => Vehicle::STATUS_AVAILABLE]);

        $attention = $this->dashboard($b)->needsAttention();
        $snapshot = $this->dashboard($b)->fleetSnapshot();

        $this->assertSame(0, $attention['total']);
        $this->assertSame(1, $snapshot['total']);
    }

    // ── Caching + polling ──────────────────────────────────────────────────

    public function test_the_cache_serves_the_second_read_and_can_be_busted(): void
    {
        $t = $this->makeTenant('dash-cache');
        $this->overdueInvoice($t, daysLate: 5);

        $cache = app(ReportCacheService::class);

        $first = $cache->dashboardOperational(true);
        $this->assertSame(1, $first['attention']['total']);

        // A new row is NOT reflected until the cache is busted — the point of
        // caching, and why the TTL is short.
        $this->overdueInvoice($t, daysLate: 6);
        $this->assertSame(1, $cache->dashboardOperational(true)['attention']['total']);

        $cache->invalidate(ReportCacheService::TYPE_DASHBOARD);
        $this->assertSame(2, $cache->dashboardOperational(true)['attention']['total']);
    }

    public function test_staff_and_admin_payloads_are_cached_separately(): void
    {
        $t = $this->makeTenant('dash-cache2');
        $this->overdueInvoice($t, daysLate: 5);

        $cache = app(ReportCacheService::class);

        $staffView = $cache->dashboardOperational(false);
        $adminView = $cache->dashboardOperational(true);

        $this->assertSame(0, $staffView['attention']['total']);
        $this->assertSame(1, $adminView['attention']['total']);
    }

    public function test_the_poll_refreshes_only_the_operational_prop(): void
    {
        $t = $this->makeTenant('dash-poll');
        $admin = $this->user($t);

        // What the front end's router.reload({ only: ['operational'] }) sends.
        // The version header must match the app's current asset version, or
        // Inertia answers 409 and asks the browser to do a full reload.
        $response = $this->actingAs($admin, 'tenant')
            ->withHeaders([
                'X-Inertia' => 'true',
                // The middleware derives the version from the Vite manifest at
                // request time — Inertia::getVersion() is empty outside one.
                'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(request()),
                'X-Inertia-Partial-Component' => 'Tenant/Dashboard',
                'X-Inertia-Partial-Data' => 'operational',
            ])
            ->get("/app/{$t->slug}/dashboard");

        $response->assertOk();

        // Asserted on the raw Inertia payload rather than assertInertia(): a
        // PARTIAL response deliberately carries only the requested prop, which
        // is exactly what is being verified here.
        $props = $response->json('props');

        $this->assertArrayHasKey('operational', $props);
        $this->assertArrayNotHasKey('planName', $props, 'a partial reload must not re-send the whole page');
        $this->assertArrayNotHasKey('summary', $props);
        $this->assertArrayHasKey('attention', $props['operational']);
    }
}
