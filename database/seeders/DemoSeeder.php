<?php

namespace Database\Seeders;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\CMS\Models\DemoRequest;
use App\Modules\CRM\Models\Lead;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Invoice\Models\Payment;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Subscription;
use App\Modules\SaasCore\Models\SubscriptionPayment;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SuperAdmin\Models\SuperAdmin;
use App\Modules\Workshop\Actions\GenerateVehicleQrAction;
use App\Modules\Workshop\Models\Mechanic;
use App\Modules\Workshop\Models\PartUsed;
use App\Modules\Workshop\Models\ServiceLog;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * DemoSeeder — pre-loads the demo server (demo.dvaro.com.au) with realistic
 * sample data across every module (CLAUDE.md: "Demo server pre-loaded with
 * realistic seed data via DemoSeeder").
 *
 * What it creates:
 *   - Roles for every guard + landing page CMS content (delegated seeders)
 *   - A demo platform_owner super admin
 *   - Three subscription plans (Starter free / Growth / Fleet Pro)
 *   - Two tenants: Coastline Car Rentals (active, rich history) and
 *     Outback Auto Hire (on trial, lighter data)
 *   - Per tenant: staff users, vehicles (with QR codes), customers, signed
 *     agreements (incl. a version chain), recurring invoices, payments,
 *     ledger entries (via LedgerService — the only sanctioned write path),
 *     leads, mechanics, service logs with parts, portal logins, and
 *     notification logs
 *
 * Idempotent per tenant: a tenant whose slug already exists is skipped
 * entirely, so re-running never duplicates operational data. NEVER run with
 * migrate:fresh on shared environments — plain `php artisan db:seed
 * --class=DemoSeeder` on a migrated database is all it needs.
 *
 * All demo logins use the password below. Amounts are integer CENTS (AUD).
 * Ledger sign convention: positive = customer owes more, negative = credit.
 * Bond lifecycle: collection is NEGATIVE (a liability — the tenant holds the
 * customer's money); deductions/refunds are POSITIVE so a completed lifecycle
 * nets to zero.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /** 1×1 transparent PNG — stands in for a canvas signature capture. */
    private const SIGNATURE_PLACEHOLDER = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private LedgerService $ledger;

    /** Rotates payment methods so recorded payments look varied. */
    private int $paymentSequence = 0;

    public function run(): void
    {
        $this->ledger = app(LedgerService::class);

        $this->call([
            SuperAdminRolesSeeder::class,
            TenantRolesSeeder::class,
            MechanicRolesSeeder::class,
            CmsContentSeeder::class,
        ]);

        $this->seedDemoSuperAdmin();

        $plans = $this->seedPlans();

        $this->seedTenant(
            slug: 'coastline',
            build: fn (Tenant $tenant) => $this->seedCoastline($tenant),
            attributes: [
                'name' => 'Coastline Car Rentals',
                'status' => Tenant::STATUS_ACTIVE,
                'plan_id' => $plans['fleet-pro']->id,
                'settings' => ['timezone' => 'Australia/Perth', 'currency' => 'AUD'],
            ],
            subscription: [
                'plan_id' => $plans['fleet-pro']->id,
                'status' => Subscription::STATUS_ACTIVE,
                'billing_cycle' => Subscription::BILLING_MONTHLY,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->startOfMonth()->addMonth(),
            ],
        );

        $this->seedTenant(
            slug: 'outback',
            build: fn (Tenant $tenant) => $this->seedOutback($tenant),
            attributes: [
                'name' => 'Outback Auto Hire',
                'status' => Tenant::STATUS_TRIAL,
                'plan_id' => $plans['growth']->id,
                'trial_ends_at' => now()->addDays(10),
                'settings' => ['timezone' => 'Australia/Sydney', 'currency' => 'AUD'],
            ],
            subscription: [
                'plan_id' => $plans['growth']->id,
                'status' => Subscription::STATUS_TRIALING,
                'billing_cycle' => Subscription::BILLING_MONTHLY,
                'trial_ends_at' => now()->addDays(10),
            ],
        );

        $this->seedDemoRequests();

        $this->printCredentials();
    }

    // ── Platform level ──────────────────────────────────────────────────

    private function seedDemoSuperAdmin(): void
    {
        $owner = SuperAdmin::firstOrCreate(
            ['email' => 'owner@dvaro.demo'],
            [
                'name' => 'Demo Platform Owner',
                'password' => Hash::make(self::PASSWORD),
                'role' => SuperAdmin::ROLE_PLATFORM_OWNER,
                'is_active' => true,
            ],
        );

        if (! $owner->hasRole(SuperAdmin::ROLE_PLATFORM_OWNER)) {
            $owner->assignRole(SuperAdmin::ROLE_PLATFORM_OWNER);
        }
    }

    /**
     * @return array<string, Plan> keyed by slug
     */
    private function seedPlans(): array
    {
        $definitions = [
            'starter' => [
                'name' => 'Starter',
                'description' => 'For owner-operators getting started — core fleet, rental and invoicing tools, free forever.',
                'price_monthly' => 0,
                'price_annual' => 0,
                'is_free' => true,
                'trial_days' => 0,
                'modules' => ['fleet', 'rental', 'agreement', 'invoice', 'customer', 'notification'],
                'limits' => [
                    'max_vehicles' => 5,
                    'max_staff_users' => 2,
                    'max_customers' => 25,
                    'max_storage_gb' => 2,
                    'max_file_size_mb' => 10,
                ],
                'sort_order' => 1,
            ],
            'growth' => [
                'name' => 'Growth',
                'description' => 'For growing rental businesses — workshop, CRM and reporting on top of the core, with room to scale.',
                'price_monthly' => 12900,
                'price_annual' => 129000,
                'is_free' => false,
                'trial_days' => 14,
                'modules' => ['fleet', 'rental', 'agreement', 'invoice', 'finance', 'workshop', 'crm', 'customer', 'reporting', 'notification'],
                'limits' => [
                    'max_vehicles' => 25,
                    'max_staff_users' => 10,
                    'max_customers' => 250,
                    'max_storage_gb' => 25,
                    'max_file_size_mb' => 25,
                ],
                'sort_order' => 2,
            ],
            'fleet-pro' => [
                'name' => 'Fleet Pro',
                'description' => 'For established fleets — every module including the AI assistant, with no limits.',
                'price_monthly' => 27900,
                'price_annual' => 279000,
                'is_free' => false,
                'trial_days' => 14,
                'modules' => Plan::MODULE_KEYS,
                'limits' => [
                    'max_vehicles' => -1,
                    'max_staff_users' => -1,
                    'max_customers' => -1,
                    'max_storage_gb' => 100,
                    'max_file_size_mb' => 50,
                ],
                'sort_order' => 3,
            ],
        ];

        $plans = [];

        foreach ($definitions as $slug => $attributes) {
            $plans[$slug] = Plan::firstOrCreate(
                ['slug' => $slug],
                $attributes + ['is_active' => true],
            );
        }

        return $plans;
    }

    private function seedDemoRequests(): void
    {
        if (DemoRequest::query()->exists()) {
            return;
        }

        DemoRequest::create([
            'company_name' => 'Swan River Rentals',
            'contact_name' => 'Hannah Cole',
            'email' => 'hannah.cole@example.com',
            'phone' => '+61 8 9455 1023',
            'message' => 'We run 18 vehicles across two Perth depots and are looking to replace spreadsheets. Keen to see the workshop module.',
            'status' => DemoRequest::STATUS_NEW,
        ]);

        DemoRequest::create([
            'company_name' => 'Cairns Tropical Cars',
            'contact_name' => 'Dean Walker',
            'email' => 'dean.walker@example.com',
            'phone' => '+61 7 4051 8877',
            'message' => 'Interested in the rideshare agreement flow and Stripe billing for about 40 vehicles.',
            'status' => DemoRequest::STATUS_CONTACTED,
        ]);
    }

    // ── Tenant scaffolding ──────────────────────────────────────────────

    /**
     * Creates the tenant + its subscription, binds it as current_tenant, and
     * runs the tenant-specific builder inside that binding. Skips entirely if
     * the slug already exists (idempotency).
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $subscription
     */
    private function seedTenant(string $slug, callable $build, array $attributes, array $subscription): void
    {
        if (Tenant::where('slug', $slug)->exists()) {
            $this->command?->warn("Tenant '{$slug}' already exists — skipping its demo data.");

            return;
        }

        $tenant = Tenant::create($attributes + ['slug' => $slug]);

        // Tenant-scoped models (TenantScope) require a bound current_tenant —
        // bind it for the duration of this tenant's seeding, exactly as
        // TenantMiddleware would in a web request.
        app()->instance('current_tenant', $tenant);

        try {
            Subscription::create($subscription + ['tenant_id' => $tenant->id]);

            $build($tenant);
        } finally {
            app()->forgetInstance('current_tenant');
        }
    }

    // ── Coastline Car Rentals — the rich tenant ─────────────────────────

    private function seedCoastline(Tenant $tenant): void
    {
        // Offline record of this month's subscription payment (super admin view).
        SubscriptionPayment::create([
            'subscription_id' => $tenant->activeSubscription->id,
            'amount' => 27900,
            'currency' => 'AUD',
            'method' => 'bank_transfer',
            'reference' => 'DVARO-SUB-2026-0107',
            'notes' => 'July subscription — paid by direct deposit.',
            'paid_at' => now()->startOfMonth(),
        ]);

        $admin = $this->tenantUser('Daniel Harper', 'admin@coastline.demo', TenantUser::ROLE_ADMIN);
        $this->tenantUser('Ruby Sanders', 'staff@coastline.demo', TenantUser::ROLE_STAFF);
        $this->tenantUser('Ken Watanabe', 'accounts@coastline.demo', TenantUser::ROLE_ACCOUNTS);

        $vehicles = $this->createVehicles([
            'corolla' => ['1GDJ482', 'Toyota', 'Corolla Ascent Sport', 2023, Vehicle::STATUS_RENTED, 6900, 'AAMI', 8, 11],
            'camry' => ['1CMR221', 'Toyota', 'Camry Hybrid', 2022, Vehicle::STATUS_RENTED, 7900, 'Allianz', 7, 9],
            'hilux' => ['1HLX905', 'Toyota', 'HiLux SR5', 2021, Vehicle::STATUS_RENTED, 9900, 'RAC WA', 10, 6],
            'cx5' => ['1MZD330', 'Mazda', 'CX-5 Touring', 2023, Vehicle::STATUS_RENTED, 8900, 'AAMI', 9, 12],
            // Insurance expiring in 12 days — exercises the expiry reminder demo.
            'i30' => ['1HYU118', 'Hyundai', 'i30 Active', 2022, Vehicle::STATUS_AVAILABLE, 6500, 'Budget Direct', 0.4, 8],
            'cerato' => ['1KIA652', 'Kia', 'Cerato Sport', 2021, Vehicle::STATUS_AVAILABLE, 6200, 'NRMA', 6, 10],
            'ranger' => ['1FRD077', 'Ford', 'Ranger XLT', 2020, Vehicle::STATUS_MAINTENANCE, 10500, 'RAC WA', 5, 7],
            'outlander' => ['1MIT414', 'Mitsubishi', 'Outlander LS', 2022, Vehicle::STATUS_RESERVED, 8500, 'Allianz', 11, 5],
            'rav4' => ['1TYT266', 'Toyota', 'RAV4 Cruiser', 2023, Vehicle::STATUS_ACCIDENT, 8900, 'AAMI', 4, 9],
            // Registration expiring in 21 days — second reminder scenario.
            'mg' => ['1MGZ539', 'MG', 'ZS Excite', 2023, Vehicle::STATUS_AVAILABLE, 5900, 'Budget Direct', 7, 0.7],
        ]);

        // Kia Cerato is overdue for service (needsService scope demo).
        $vehicles['cerato']->update([
            'last_service_date' => now()->subMonths(7)->toDateString(),
            'next_service_due' => now()->subDays(9)->toDateString(),
        ]);

        $customers = [
            'sarah' => $this->customer('Sarah Mitchell', 'sarah.mitchell@example.com', '+61 412 336 904', 'WA', risk: null),
            'james' => $this->customer('James Nguyen', 'james.nguyen@example.com', '+61 401 552 718', 'WA', risk: 'Second reminder sent for the current invoice — monitor before extending.'),
            'tom' => $this->customer("Tom O'Brien", 'tom.obrien@example.com', '+61 438 220 165', 'WA', risk: null),
            'priya' => $this->customer('Priya Sharma', 'priya.sharma@example.com', '+61 422 908 331', 'WA', risk: null),
            'emma' => $this->customer('Emma Wilson', 'emma.wilson@example.com', '+61 417 664 209', 'WA', risk: null),
            'liam' => $this->customer('Liam Chen', 'liam.chen@example.com', '+61 435 118 472', 'WA', risk: null),
        ];

        $blacklisted = $this->customer('Marcus Reid', 'marcus.reid@example.com', '+61 409 771 583', 'WA', risk: 'Returned vehicle with undisclosed damage twice.');
        $blacklisted->update([
            'is_blacklisted' => true,
            'blacklisted_reason' => 'Repeated undisclosed damage and two dishonoured payments (Feb & Apr 2026).',
        ]);

        // Sarah — rideshare, with a vehicle change: v1 on the i30 (completed),
        // v2 on the Corolla (active). The version chain demos agreement
        // immutability + versioning.
        $sarahV1 = $this->agreement($customers['sarah'], $vehicles['i30'], Agreement::TYPE_RIDESHARE, Agreement::STATUS_COMPLETED, 26000, 50000, now()->subWeeks(8), now()->subWeeks(3));
        $sarahV2 = $this->agreement($customers['sarah'], $vehicles['corolla'], Agreement::TYPE_RIDESHARE, Agreement::STATUS_ACTIVE, 28000, 50000, now()->subWeeks(3), null, version: 2, parent: $sarahV1);
        $this->collectBond($tenant, $customers['sarah'], $sarahV1, 50000);
        $this->weeklyInvoices($sarahV1, $vehicles['i30'], paidWeeks: 5);
        $this->weeklyInvoices($sarahV2, $vehicles['corolla'], paidWeeks: 2, currentStatus: Invoice::STATUS_SENT);

        // James — private hire with the CURRENT invoice overdue + late fee.
        $james = $this->agreement($customers['james'], $vehicles['camry'], Agreement::TYPE_PRIVATE, Agreement::STATUS_ACTIVE, 32000, 25000, now()->subWeeks(5), null);
        $this->collectBond($tenant, $customers['james'], $james, 25000);
        $this->weeklyInvoices($james, $vehicles['camry'], paidWeeks: 4, currentStatus: Invoice::STATUS_OVERDUE, lateFee: 2500);

        // Tom — delivery driver on the HiLux.
        $tom = $this->agreement($customers['tom'], $vehicles['hilux'], Agreement::TYPE_DELIVERY, Agreement::STATUS_ACTIVE, 42000, 60000, now()->subWeeks(6), null);
        $this->collectBond($tenant, $customers['tom'], $tom, 60000);
        $this->weeklyInvoices($tom, $vehicles['hilux'], paidWeeks: 5, currentStatus: Invoice::STATUS_SENT);

        // Priya — recent private hire on the CX-5.
        $priya = $this->agreement($customers['priya'], $vehicles['cx5'], Agreement::TYPE_PRIVATE, Agreement::STATUS_ACTIVE, 36000, 50000, now()->subWeeks(2), null);
        $this->collectBond($tenant, $customers['priya'], $priya, 50000);
        $this->weeklyInvoices($priya, $vehicles['cx5'], paidWeeks: 1, currentStatus: Invoice::STATUS_SENT);

        // Emma — completed rental with a bond deduction (windscreen chip).
        $emma = $this->agreement($customers['emma'], $vehicles['cerato'], Agreement::TYPE_PRIVATE, Agreement::STATUS_COMPLETED, 30000, 60000, now()->subWeeks(12), now()->subWeeks(4));
        $this->collectBond($tenant, $customers['emma'], $emma, 60000);
        $this->weeklyInvoices($emma, $vehicles['cerato'], paidWeeks: 8);
        $this->refundBond($tenant, $customers['emma'], $emma, 60000, deduction: 12500, deductionReason: 'Windscreen chip repair');

        // Liam — completed rental, fully refunded bond. Converted from a lead.
        $liam = $this->agreement($customers['liam'], $vehicles['i30'], Agreement::TYPE_RIDESHARE, Agreement::STATUS_COMPLETED, 26000, 40000, now()->subWeeks(20), now()->subWeeks(14));
        $this->collectBond($tenant, $customers['liam'], $liam, 40000);
        $this->weeklyInvoices($liam, $vehicles['i30'], paidWeeks: 6);
        $this->refundBond($tenant, $customers['liam'], $liam, 40000);

        // Customer portal logins for two customers.
        foreach (['sarah', 'priya'] as $key) {
            CustomerUser::create([
                'customer_id' => $customers[$key]->id,
                'email' => $customers[$key]->email,
                'password' => self::PASSWORD,
            ]);
        }

        // CRM pipeline — one converted (Liam), one contacted, one freshly
        // submitted, one link generated but not yet used.
        Lead::create([
            'name' => 'Liam Chen', 'email' => 'liam.chen@example.com', 'phone' => '+61 435 118 472',
            'status' => Lead::STATUS_CONVERTED, 'token' => (string) Str::uuid(),
            'rental_start_date' => now()->subWeeks(20)->toDateString(), 'rental_duration' => '6 weeks',
            'submitted_at' => now()->subWeeks(21), 'converted_at' => now()->subWeeks(20),
            'converted_customer_id' => $customers['liam']->id, 'created_by' => $admin->id,
        ]);
        Lead::create([
            'name' => 'Olivia Martin', 'email' => 'olivia.martin@example.com', 'phone' => '+61 447 902 615',
            'status' => Lead::STATUS_CONTACTED, 'token' => (string) Str::uuid(),
            'rental_start_date' => now()->addWeeks(2)->toDateString(), 'rental_duration' => '3 months',
            'notes' => 'Needs an SUV for a regional contract. Following up Thursday.',
            'submitted_at' => now()->subDays(4), 'created_by' => $admin->id,
        ]);
        Lead::create([
            'name' => 'Nathan Brooks', 'email' => 'nathan.brooks@example.com', 'phone' => '+61 413 550 187',
            'status' => Lead::STATUS_NEW, 'token' => (string) Str::uuid(),
            'rental_start_date' => now()->addDays(3)->toDateString(), 'rental_duration' => 'No fixed term',
            'notes' => 'Rideshare driver, prefers a hybrid.',
            'submitted_at' => now()->subDay(), 'created_by' => $admin->id,
        ]);
        Lead::create([
            'name' => 'Ryan Foster', 'email' => 'ryan.foster@example.com', 'phone' => '+61 428 664 930',
            'status' => Lead::STATUS_NEW, 'token' => (string) Str::uuid(),
            'token_expires_at' => now()->addDays(7), 'created_by' => $admin->id,
        ]);

        // Workshop — two mechanics and a spread of the five maintenance statuses.
        $dave = $this->mechanic('Dave Thompson', 'dave@coastline.demo', '+61 419 227 483', Mechanic::ROLE_SENIOR);
        $alex = $this->mechanic('Alex Turner', 'alex@coastline.demo', '+61 431 809 254', Mechanic::ROLE_MECHANIC);

        $this->serviceLog($vehicles['ranger'], $dave, ServiceLog::STATUS_IN_PROGRESS, 'Clutch replacement', 'Clutch slipping under load — replacing clutch kit and resurfacing flywheel.', 84210, 38000, startedAt: now()->subDays(2));
        $this->serviceLog($vehicles['rav4'], $dave, ServiceLog::STATUS_WAITING_FOR_PARTS, 'Front-end accident repair', 'Front bumper, grille and left headlight assembly on order after low-speed collision.', 31480, 52000, startedAt: now()->subDays(6));
        $this->serviceLog($vehicles['cerato'], $alex, ServiceLog::STATUS_PENDING, '60,000 km scheduled service', 'Overdue logbook service — booked for this week.', 60310, 0);
        $this->serviceLog($vehicles['outlander'], $alex, ServiceLog::STATUS_RE_INSPECTION_REQUIRED, 'Brake shudder investigation', 'Rotors machined; customer to confirm shudder resolved before release.', 47932, 22000, startedAt: now()->subDays(4), completedAt: now()->subDay());

        $oilService = $this->serviceLog($vehicles['corolla'], $alex, ServiceLog::STATUS_COMPLETED, '40,000 km logbook service', 'Oil and filter change, tyre rotation, full inspection — no faults found.', 40125, 12000, startedAt: now()->subWeeks(3), completedAt: now()->subWeeks(3)->addHours(3));
        $this->part($oilService, 'Engine oil 5W-30 (5L)', 1, 6500);
        $this->part($oilService, 'Oil filter', 1, 1900);

        $brakeJob = $this->serviceLog($vehicles['camry'], $dave, ServiceLog::STATUS_COMPLETED, 'Front brake pads & rotors', 'Pads below 2mm — replaced pads and machined rotors.', 68740, 18000, startedAt: now()->subWeeks(6), completedAt: now()->subWeeks(6)->addHours(4));
        $this->part($brakeJob, 'Front brake pad set', 1, 8900);
        $this->part($brakeJob, 'Rotor machining (pair)', 1, 6000);

        // A few notification log entries so the comms history isn't empty.
        $this->notificationLog(NotificationLog::CHANNEL_EMAIL, 'payment_received', $customers['sarah']->email, 'Payment received — thank you', NotificationLog::TYPE_CUSTOMER, $customers['sarah']->id, now()->subDays(3));
        $this->notificationLog(NotificationLog::CHANNEL_SMS, 'invoice_overdue', '+61 401 552 718', 'Your Coastline invoice is overdue', NotificationLog::TYPE_CUSTOMER, $customers['james']->id, now()->subDays(2));
        $this->notificationLog(NotificationLog::CHANNEL_EMAIL, 'insurance_expiry', 'admin@coastline.demo', 'Insurance expiring soon — Hyundai i30 (1HYU118)', NotificationLog::TYPE_VEHICLE, $vehicles['i30']->id, now()->subDay());
        $this->notificationLog(NotificationLog::CHANNEL_WHATSAPP, 'lead_submitted', '+61 419 000 111', 'New lead: Nathan Brooks', NotificationLog::TYPE_TENANT_USER, $admin->id, now()->subDay());
    }

    // ── Outback Auto Hire — the trial tenant ────────────────────────────

    private function seedOutback(Tenant $tenant): void
    {
        $this->tenantUser('Mia Robertson', 'admin@outback.demo', TenantUser::ROLE_ADMIN);

        $vehicles = $this->createVehicles([
            'landcruiser' => ['ZKW14D', 'Toyota', 'LandCruiser GXL', 2022, Vehicle::STATUS_RENTED, 14500, 'NRMA', 9, 8],
            'navara' => ['ZTB92F', 'Nissan', 'Navara ST-X', 2021, Vehicle::STATUS_AVAILABLE, 9800, 'NRMA', 6, 10],
            'swift' => ['ZQL37H', 'Suzuki', 'Swift GL', 2023, Vehicle::STATUS_AVAILABLE, 5500, 'Allianz', 11, 7],
            'dmax' => ['ZPR68J', 'Isuzu', 'D-MAX LS-U', 2020, Vehicle::STATUS_MAINTENANCE, 9500, 'RAC WA', 5, 6],
        ]);

        $jack = $this->customer('Jack Morrison', 'jack.morrison@example.com', '+61 427 385 902', 'NSW', risk: null);
        $chloe = $this->customer('Chloe Bennett', 'chloe.bennett@example.com', '+61 415 662 048', 'NSW', risk: null);

        $jackAgreement = $this->agreement($jack, $vehicles['landcruiser'], Agreement::TYPE_PRIVATE, Agreement::STATUS_ACTIVE, 65000, 80000, now()->subWeeks(2), null);
        $this->collectBond($tenant, $jack, $jackAgreement, 80000);
        $this->weeklyInvoices($jackAgreement, $vehicles['landcruiser'], paidWeeks: 1, currentStatus: Invoice::STATUS_SENT);

        $chloeAgreement = $this->agreement($chloe, $vehicles['swift'], Agreement::TYPE_PRIVATE, Agreement::STATUS_COMPLETED, 28000, 30000, now()->subWeeks(6), now()->subWeeks(3));
        $this->collectBond($tenant, $chloe, $chloeAgreement, 30000);
        $this->weeklyInvoices($chloeAgreement, $vehicles['swift'], paidWeeks: 3);
        $this->refundBond($tenant, $chloe, $chloeAgreement, 30000);

        $ben = $this->mechanic('Ben Carter', 'ben@outback.demo', '+61 429 116 380', Mechanic::ROLE_MECHANIC);
        $this->serviceLog($vehicles['dmax'], $ben, ServiceLog::STATUS_IN_PROGRESS, 'Suspension overhaul', 'Rear leaf springs sagging under load — replacing springs and shocks.', 92615, 26000, startedAt: now()->subDay());

        Lead::create([
            'name' => 'Sophie Lawson', 'email' => 'sophie.lawson@example.com', 'phone' => '+61 402 771 946',
            'status' => Lead::STATUS_NEW, 'token' => (string) Str::uuid(),
            'rental_start_date' => now()->addWeek()->toDateString(), 'rental_duration' => '4 weeks',
            'submitted_at' => now()->subHours(6),
        ]);
    }

    // ── Record builders ─────────────────────────────────────────────────

    private function tenantUser(string $name, string $email, string $role): TenantUser
    {
        $user = TenantUser::create([
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'role' => $role,
        ]);

        $user->assignRole($role);

        return $user;
    }

    /**
     * Rows: [rego, make, model, year, status, daily_rate, insurer,
     * insurance_expiry_months, rego_expiry_months] — fractional months give
     * the near-expiry reminder scenarios. Generates each vehicle's QR code
     * through the sanctioned action.
     *
     * @param  array<string, array{0: string, 1: string, 2: string, 3: int, 4: string, 5: int, 6: string, 7: float|int, 8: float|int}>  $rows
     * @return array<string, Vehicle>
     */
    private function createVehicles(array $rows): array
    {
        $qr = app(GenerateVehicleQrAction::class);
        $vehicles = [];

        foreach ($rows as $key => [$rego, $make, $model, $year, $status, $rate, $insurer, $insuranceMonths, $regoMonths]) {
            $vehicle = Vehicle::create([
                'registration_number' => $rego,
                'make' => $make,
                'model' => $model,
                'year' => $year,
                'status' => $status,
                'daily_rate' => $rate,
                'insurance_company' => $insurer,
                'insurance_expiry' => now()->addDays((int) round($insuranceMonths * 30))->toDateString(),
                'registration_expiry' => now()->addDays((int) round($regoMonths * 30))->toDateString(),
                'last_service_date' => now()->subMonths(3)->toDateString(),
                'next_service_due' => now()->addMonths(3)->toDateString(),
            ]);

            $vehicles[$key] = $qr->execute($vehicle);
        }

        return $vehicles;
    }

    private function customer(string $name, string $email, string $phone, string $state, ?string $risk): Customer
    {
        static $licence = 6103200;

        return Customer::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'date_of_birth' => now()->subYears(rand(24, 52))->subDays(rand(0, 364))->toDateString(),
            'licence_number' => $state.(++$licence),
            'licence_expiry' => now()->addYears(rand(1, 4))->toDateString(),
            'address' => rand(1, 320).' '.collect(['Marine Terrace', 'Stirling Highway', 'Canning Road', 'Ocean Drive', 'Forrest Street'])->random().', '.($state === 'WA' ? 'Perth WA' : 'Dubbo NSW'),
            'emergency_contact_name' => collect(['Alan', 'Karen', 'Robert', 'Helen', 'Peter'])->random().' '.explode(' ', $name)[1],
            'emergency_contact_phone' => '+61 4'.rand(10, 59).' '.rand(100, 999).' '.rand(100, 999),
            'risk_notes' => $risk,
            'is_blacklisted' => false,
        ]);
    }

    private function agreement(Customer $customer, Vehicle $vehicle, string $type, string $status, int $weeklyRate, int $bond, Carbon $start, ?Carbon $end, int $version = 1, ?Agreement $parent = null): Agreement
    {
        $active = $status === Agreement::STATUS_ACTIVE;

        return Agreement::create([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'type' => $type,
            'status' => $status,
            'version' => $version,
            'parent_agreement_id' => $parent?->id,
            'billing_cycle' => Agreement::BILLING_WEEKLY,
            'rate' => $weeklyRate,
            'bond_amount' => $bond,
            'start_date' => $start->toDateString(),
            'end_date' => $end?->toDateString(),
            'next_billing_date' => $active ? $this->currentPeriodStart($start)->addWeek()->toDateString() : null,
            'signed_at' => $start->copy()->subDay(),
            'signature_data' => self::SIGNATURE_PLACEHOLDER,
        ]);
    }

    /**
     * The start of the weekly billing period that contains today, for an
     * agreement that started at $start.
     */
    private function currentPeriodStart(Carbon $start): Carbon
    {
        return $start->copy()->addWeeks((int) floor($start->diffInWeeks(now())));
    }

    /**
     * Seeds $paidWeeks fully paid weekly invoices from the agreement start,
     * then (optionally) the current period's invoice as sent or overdue.
     * Every invoice writes its rental charge — and payment, when paid — to
     * the ledger through LedgerService.
     */
    private function weeklyInvoices(Agreement $agreement, Vehicle $vehicle, int $paidWeeks, ?string $currentStatus = null, int $lateFee = 0): void
    {
        $start = $agreement->start_date->copy();

        for ($week = 0; $week < $paidWeeks; $week++) {
            $this->invoice($agreement, $vehicle, $start->copy()->addWeeks($week), Invoice::STATUS_PAID);
        }

        if ($currentStatus !== null) {
            // The overdue scenario bills the PREVIOUS period (its due date has
            // lapsed); a sent invoice bills the current one.
            $periodStart = $this->currentPeriodStart($start);

            if ($currentStatus === Invoice::STATUS_OVERDUE) {
                $periodStart = $periodStart->subWeek();
            }

            $this->invoice($agreement, $vehicle, $periodStart, $currentStatus, $lateFee);
        }
    }

    private function invoice(Agreement $agreement, Vehicle $vehicle, Carbon $periodStart, string $status, int $lateFee = 0): Invoice
    {
        $periodEnd = $periodStart->copy()->addDays(6);
        $rate = $agreement->rate;
        $total = $rate + $lateFee;
        $paid = $status === Invoice::STATUS_PAID;
        $label = "Weekly rental — {$vehicle->make} {$vehicle->model} ({$vehicle->registration_number})";
        $period = $periodStart->format('j M').' – '.$periodEnd->format('j M Y');

        $invoice = Invoice::create([
            'customer_id' => $agreement->customer_id,
            'agreement_id' => $agreement->id,
            'type' => Invoice::TYPE_RECURRING,
            'status' => $status,
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'due_date' => $periodStart->toDateString(),
            'subtotal' => $rate,
            'total' => $total,
            'paid_amount' => $paid ? $total : 0,
        ]);

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'description' => $label,
            'amount' => $rate,
            'vehicle_id' => $vehicle->id,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
        ]);

        $this->ledger->append($agreement->tenant_id, $agreement->customer_id, LedgerEntry::TYPE_RENTAL_CHARGE, $rate, "{$label} · {$period}", 'invoice', $invoice->id);

        if ($lateFee > 0) {
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'description' => 'Late payment fee',
                'amount' => $lateFee,
            ]);

            $this->ledger->append($agreement->tenant_id, $agreement->customer_id, LedgerEntry::TYPE_LATE_FEE, $lateFee, "Late fee — invoice #{$invoice->id} ({$period})", 'invoice', $invoice->id);
        }

        if ($paid) {
            $method = [Payment::METHOD_BANK_TRANSFER, Payment::METHOD_STRIPE, Payment::METHOD_CASH][$this->paymentSequence++ % 3];

            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'customer_id' => $agreement->customer_id,
                'amount' => $total,
                'method' => $method,
                'gateway_payment_id' => $method === Payment::METHOD_STRIPE ? 'pi_demo_'.Str::lower(Str::random(16)) : null,
                'paid_at' => $periodStart->copy()->addDay(),
            ]);

            $this->ledger->append($agreement->tenant_id, $agreement->customer_id, LedgerEntry::TYPE_PAYMENT, -$total, "Payment received ({$method}) — invoice #{$invoice->id}", 'payment', $payment->id);
        }

        return $invoice;
    }

    /**
     * Bond collected at rental creation — a LIABILITY, so the entry is
     * negative (the tenant holds the customer's money).
     */
    private function collectBond(Tenant $tenant, Customer $customer, Agreement $agreement, int $amount): void
    {
        $this->ledger->append($tenant->id, $customer->id, LedgerEntry::TYPE_BOND_COLLECTION, -$amount, 'Bond collected — agreement #'.$agreement->id, 'agreement', $agreement->id);
    }

    /**
     * Bond released at return: optional damage deduction kept by the tenant,
     * remainder refunded. Both entries are positive so the full bond
     * lifecycle nets to zero.
     */
    private function refundBond(Tenant $tenant, Customer $customer, Agreement $agreement, int $bond, int $deduction = 0, ?string $deductionReason = null): void
    {
        if ($deduction > 0) {
            $this->ledger->append($tenant->id, $customer->id, LedgerEntry::TYPE_BOND_DEDUCTION, $deduction, ($deductionReason ?? 'Damage deduction').' — deducted from bond, agreement #'.$agreement->id, 'agreement', $agreement->id);
        }

        $this->ledger->append($tenant->id, $customer->id, LedgerEntry::TYPE_BOND_REFUND, $bond - $deduction, 'Bond refunded — agreement #'.$agreement->id, 'agreement', $agreement->id);
    }

    private function mechanic(string $name, string $email, string $phone, string $role): Mechanic
    {
        $mechanic = Mechanic::create([
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'pin' => '1234',
            'password' => self::PASSWORD,
            'is_active' => true,
            'last_login_at' => now()->subDays(rand(0, 3)),
        ]);

        $mechanic->assignRole($role);

        return $mechanic;
    }

    private function serviceLog(Vehicle $vehicle, Mechanic $mechanic, string $status, string $title, string $description, int $odometer, int $labour, ?Carbon $startedAt = null, ?Carbon $completedAt = null): ServiceLog
    {
        return ServiceLog::create([
            'vehicle_id' => $vehicle->id,
            'mechanic_id' => $mechanic->id,
            'status' => $status,
            'title' => $title,
            'description' => $description,
            'odometer_reading' => $odometer,
            'labour_cost' => $labour,
            'total_cost' => $labour,
            'started_at' => $startedAt,
            'completed_at' => $completedAt,
        ]);
    }

    private function part(ServiceLog $log, string $name, int $quantity, int $unitCost): void
    {
        PartUsed::create([
            'service_log_id' => $log->id,
            'name' => $name,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'total_cost' => $quantity * $unitCost,
        ]);

        // Keep the log's total in sync with labour + parts.
        $log->update(['total_cost' => $log->total_cost + $quantity * $unitCost]);
    }

    private function notificationLog(string $channel, string $eventType, string $recipient, string $subject, string $notifiableType, int $notifiableId, Carbon $sentAt): void
    {
        NotificationLog::create([
            'notifiable_type' => $notifiableType,
            'notifiable_id' => $notifiableId,
            'channel' => $channel,
            'event_type' => $eventType,
            'recipient' => $recipient,
            'subject' => $subject,
            'body' => $subject.'.',
            'status' => NotificationLog::STATUS_SENT,
            'provider' => $channel === NotificationLog::CHANNEL_EMAIL ? 'smtp' : 'clicksend',
            'sent_at' => $sentAt,
        ]);
    }

    private function printCredentials(): void
    {
        $this->command?->info('Demo data seeded. All passwords: "'.self::PASSWORD.'" (mechanic PIN: 1234).');

        $this->command?->table(
            ['Portal', 'Path', 'Login'],
            [
                ['Super Admin', '/superadmin', 'owner@dvaro.demo'],
                ['Tenant — Coastline (admin)', '/app/coastline', 'admin@coastline.demo'],
                ['Tenant — Coastline (staff)', '/app/coastline', 'staff@coastline.demo'],
                ['Tenant — Coastline (accounts)', '/app/coastline', 'accounts@coastline.demo'],
                ['Tenant — Outback (admin)', '/app/outback', 'admin@outback.demo'],
                ['Customer portal', '/portal/coastline', 'sarah.mitchell@example.com'],
                ['Customer portal', '/portal/coastline', 'priya.sharma@example.com'],
                ['Mechanic portal', '/mechanic/coastline', 'dave@coastline.demo'],
                ['Mechanic portal', '/mechanic/outback', 'ben@outback.demo'],
            ],
        );
    }
}
