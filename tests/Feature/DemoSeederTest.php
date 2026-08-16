<?php

namespace Tests\Feature;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\ServiceLog;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Exercises DemoSeeder end-to-end: the demo server depends on this seeder
 * producing a coherent dataset (tenants, scoped operational data, balanced
 * ledgers) and on it being safely re-runnable.
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class DemoSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // QR generation writes SVGs to the public disk — keep the real
        // storage directory clean.
        Storage::fake('public');
    }

    public function test_seeds_a_complete_coherent_demo_dataset(): void
    {
        $this->seed(DemoSeeder::class);

        // Platform level.
        $this->assertTrue(SuperAdmin::where('email', 'owner@dvaro.demo')->exists());
        $this->assertGreaterThanOrEqual(3, Plan::whereIn('slug', ['starter', 'growth', 'fleet-pro'])->count());

        $coastline = Tenant::where('slug', 'coastline')->firstOrFail();
        $outback = Tenant::where('slug', 'outback')->firstOrFail();

        $this->assertSame(Tenant::STATUS_ACTIVE, $coastline->status);
        $this->assertSame(Tenant::STATUS_TRIAL, $outback->status);
        $this->assertNotNull($coastline->activeSubscription);
        $this->assertNotNull($outback->activeSubscription);

        // Coastline operational data (tenant-scoped — bind like middleware would).
        app()->instance('current_tenant', $coastline);

        try {
            $this->assertSame(10, Vehicle::count());
            $this->assertSame(7, Customer::count());
            $this->assertSame(3, TenantUser::count());
            $this->assertSame(7, Agreement::count());
            $this->assertSame(2, Mechanic::count());
            $this->assertSame(6, ServiceLog::count());
            $this->assertSame(2, CustomerUser::count());
            $this->assertSame(1, Customer::blacklisted()->count());

            // Every seeded vehicle carries a QR token (mechanic scan flow).
            $this->assertSame(0, Vehicle::whereNull('qr_code_token')->count());

            // The agreement version chain: v2 active, pointing at a completed v1.
            $v2 = Agreement::where('version', 2)->sole();
            $this->assertSame(Agreement::STATUS_ACTIVE, $v2->status);
            $this->assertSame(Agreement::STATUS_COMPLETED, $v2->parentAgreement->status);

            // Invoice statuses cover the demo scenarios.
            $this->assertSame(1, Invoice::where('status', Invoice::STATUS_OVERDUE)->count());
            $this->assertGreaterThanOrEqual(3, Invoice::where('status', Invoice::STATUS_SENT)->count());
            $this->assertGreaterThan(15, Invoice::where('status', Invoice::STATUS_PAID)->count());

            // Paid invoices are fully paid; ledger balances are coherent:
            // James owes exactly his overdue week + late fee (32000 + 2500),
            // less nothing — his bond (25000) nets against it. Emma's completed
            // lifecycle (rent paid, bond deducted + refunded) nets to zero.
            $ledger = app(LedgerService::class);
            $james = Customer::where('email', 'james.nguyen@example.com')->sole();
            $emma = Customer::where('email', 'emma.wilson@example.com')->sole();
            $this->assertSame(32000 + 2500 - 25000, $ledger->getBalance($james->id));
            $this->assertSame(0, $ledger->getBalance($emma->id));
            $this->assertTrue($james->hasOutstandingBalance());
            $this->assertFalse($emma->hasOutstandingBalance());
        } finally {
            app()->forgetInstance('current_tenant');
        }

        // Outback data is isolated from Coastline's.
        app()->instance('current_tenant', $outback);

        try {
            $this->assertSame(4, Vehicle::count());
            $this->assertSame(2, Customer::count());
            $this->assertSame(2, Agreement::count());
            $this->assertSame(1, Mechanic::count());
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }

    public function test_rerunning_the_seeder_never_duplicates_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, Tenant::where('slug', 'coastline')->count());
        $this->assertSame(1, Tenant::where('slug', 'outback')->count());
        $this->assertSame(1, Plan::where('slug', 'fleet-pro')->count());

        app()->instance('current_tenant', Tenant::where('slug', 'coastline')->firstOrFail());

        try {
            $this->assertSame(10, Vehicle::count());
            $this->assertSame(7, Customer::count());
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }
}
