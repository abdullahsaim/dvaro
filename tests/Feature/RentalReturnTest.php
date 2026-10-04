<?php

namespace Tests\Feature;

use App\Exceptions\AppendOnlyException;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Rental\Models\ReturnInspection;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Completes the Rental module: bond collection + vehicle→Rented at signing
 * (AgreementService::sign()), and the vehicle-return/bond-settlement flow
 * (RentalReturnService::completeReturn()). Together with AgreementSigned,
 * ReturnInspectionCompleted IS the "RentalEnded" moment (CLAUDE.md) — there is
 * no separate Rental entity or duplicate pair of events.
 */
class RentalReturnTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE,
        ]);
        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function makeAdmin(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Admin', 'email' => 'admin-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    private function makeSignedAgreement(Tenant $tenant, array $agreementAttrs = []): Agreement
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Jordan Blake', 'email' => 'jordan-'.uniqid().'@test.au', 'phone' => '0400111222',
            'licence_number' => 'WA'.random_int(1000000, 9999999), 'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '1',
        ]);

        $vehicle = Vehicle::create([
            'registration_number' => 'RTN'.random_int(100, 999), 'make' => 'Toyota', 'model' => 'Camry',
            'year' => 2023, 'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 8000,
            'current_odometer' => 10000,
        ]);

        $agreement = Agreement::create(array_merge([
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'type' => 'private',
            'status' => Agreement::STATUS_DRAFT,
            'billing_cycle' => 'weekly',
            'rate' => 35000,
            'bond_amount' => 50000,
            'start_date' => today(),
            'version' => 1,
            'terms_html' => '<p>Sample frozen terms.</p>',
        ], $agreementAttrs));

        $signed = app(AgreementService::class)->sign($agreement, 'data:image/png;base64,'.base64_encode('sig'));

        // sign() dispatches AgreementSigned; its queued listener's
        // forgetTenant() clears current_tenant under a sync queue connection
        // (same footgun as PublicAgreementSigningController::submit()) — rebind
        // so every caller of this helper can keep querying tenant-scoped data.
        app()->instance('current_tenant', $tenant);

        return $signed;
    }

    // ── Signing: bond collection + vehicle→Rented ───────────────────────────

    public function test_signing_collects_the_bond_and_puts_the_vehicle_on_road(): void
    {
        $t = $this->makeTenant('rental-sign');
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 75000]);

        $this->assertSame(Vehicle::STATUS_RENTED, $agreement->vehicle->fresh()->status);

        $entry = LedgerEntry::where('reference_type', 'agreement')
            ->where('reference_id', $agreement->id)
            ->where('type', LedgerEntry::TYPE_BOND_COLLECTION)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame(75000, $entry->amount);
        $this->assertSame(75000, app(LedgerService::class)->bondHeld($agreement->id));
    }

    public function test_signing_with_no_bond_writes_no_bond_entry(): void
    {
        $t = $this->makeTenant('rental-no-bond');
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 0]);

        $this->assertSame(
            0,
            LedgerEntry::where('reference_type', 'agreement')->where('reference_id', $agreement->id)->count(),
        );
        $this->assertSame(Vehicle::STATUS_RENTED, $agreement->vehicle->fresh()->status);
    }

    public function test_bond_entries_are_excluded_from_the_customer_rental_balance(): void
    {
        $t = $this->makeTenant('rental-balance');
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        // Signing also raises the first invoice (GenerateFirstInvoice, a
        // synchronous listener on AgreementSigned), which legitimately adds a
        // rental_charge entry for the agreement's rate. getBalance() must
        // reflect THAT, but never the 50000 bond on top of it.
        $rentalChargeTotal = (int) LedgerEntry::where('customer_id', $agreement->customer_id)
            ->where('type', LedgerEntry::TYPE_RENTAL_CHARGE)
            ->sum('amount');

        $this->assertGreaterThan(0, $rentalChargeTotal);
        $this->assertSame($rentalChargeTotal, app(LedgerService::class)->getBalance($agreement->customer_id));
        $this->assertSame(50000, app(LedgerService::class)->bondHeld($agreement->id));
    }

    // ── Returning: happy path, no damage, full refund ───────────────────────

    public function test_completing_a_return_with_no_damage_refunds_the_full_bond(): void
    {
        $t = $this->makeTenant('rental-return-ok');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10500,
                'fuel_level' => ReturnInspection::FUEL_FULL,
                'damage_found' => false,
                'needs_workshop' => false,
                'deduction_amount' => 0,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        app()->instance('current_tenant', $t);
        $inspection = ReturnInspection::where('agreement_id', $agreement->id)->firstOrFail();

        $this->assertSame(10500, $inspection->odometer_reading);
        $this->assertSame(0, $inspection->deduction_amount);
        $this->assertSame(50000, $inspection->refund_amount);
        $this->assertSame(Agreement::STATUS_COMPLETED, $agreement->fresh()->status);
        $this->assertSame(Vehicle::STATUS_AVAILABLE, $agreement->vehicle->fresh()->status);
        $this->assertSame(10500, $agreement->vehicle->fresh()->current_odometer);

        $refundEntry = LedgerEntry::where('reference_type', 'agreement')
            ->where('reference_id', $agreement->id)
            ->where('type', LedgerEntry::TYPE_BOND_REFUND)
            ->first();
        $this->assertNotNull($refundEntry);
        $this->assertSame(-50000, $refundEntry->amount);

        // Fully refunded bond nets to exactly 0.
        $this->assertSame(0, app(LedgerService::class)->bondHeld($agreement->id));

        $this->assertSame(
            OdometerReading::SOURCE_RETURN_INSPECTION,
            OdometerReading::where('vehicle_id', $agreement->vehicle_id)->latest('id')->first()->source,
        );
    }

    // ── Returning: damage + partial deduction ───────────────────────────────

    public function test_completing_a_return_with_damage_deducts_from_the_bond(): void
    {
        $t = $this->makeTenant('rental-return-damage');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10200,
                'fuel_level' => ReturnInspection::FUEL_HALF,
                'damage_found' => true,
                'damage_description' => 'Scratch on rear bumper',
                'needs_workshop' => true,
                'deduction_amount' => 20000,
                'deduction_reason' => 'Bumper repair',
            ])
            ->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $inspection = ReturnInspection::where('agreement_id', $agreement->id)->firstOrFail();

        $this->assertSame(20000, $inspection->deduction_amount);
        $this->assertSame(30000, $inspection->refund_amount);
        $this->assertSame(Vehicle::STATUS_MAINTENANCE, $agreement->vehicle->fresh()->status);

        $deductionEntry = LedgerEntry::where('reference_type', 'agreement')
            ->where('reference_id', $agreement->id)
            ->where('type', LedgerEntry::TYPE_BOND_DEDUCTION)
            ->first();
        $this->assertSame(-20000, $deductionEntry->amount);

        $this->assertSame(0, app(LedgerService::class)->bondHeld($agreement->id));
    }

    public function test_a_fully_withheld_bond_writes_no_refund_entry(): void
    {
        $t = $this->makeTenant('rental-return-withheld');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10200,
                'fuel_level' => ReturnInspection::FUEL_HALF,
                'damage_found' => true,
                'damage_description' => 'Written off panel',
                'needs_workshop' => true,
                'deduction_amount' => 50000,
                'deduction_reason' => 'Full damage cost',
            ])
            ->assertSessionHasNoErrors();

        app()->instance('current_tenant', $t);
        $agreementId = $agreement->id;

        $this->assertSame(
            0,
            LedgerEntry::where('reference_type', 'agreement')
                ->where('reference_id', $agreementId)
                ->where('type', LedgerEntry::TYPE_BOND_REFUND)
                ->count(),
        );
        $this->assertSame(0, app(LedgerService::class)->bondHeld($agreementId));
    }

    // ── Validation / guards ──────────────────────────────────────────────────

    public function test_deduction_cannot_exceed_the_bond(): void
    {
        $t = $this->makeTenant('rental-return-overdraft');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10200,
                'fuel_level' => ReturnInspection::FUEL_HALF,
                'damage_found' => true,
                'damage_description' => 'Severe damage',
                'needs_workshop' => true,
                'deduction_amount' => 60000,
                'deduction_reason' => 'Exceeds bond',
            ])
            ->assertSessionHasErrors('deduction_amount');

        $this->assertSame(Agreement::STATUS_SIGNED, $agreement->fresh()->status);
        $this->assertSame(0, ReturnInspection::where('agreement_id', $agreement->id)->count());
    }

    public function test_a_draft_agreement_cannot_be_returned(): void
    {
        $t = $this->makeTenant('rental-return-draft');
        $admin = $this->makeAdmin($t);

        app()->instance('current_tenant', $t);
        $customer = Customer::create([
            'name' => 'Draft Customer', 'email' => 'draft-'.uniqid().'@test.au', 'phone' => '0400111333',
            'licence_number' => 'WA'.random_int(1000000, 9999999), 'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '1',
        ]);
        $vehicle = Vehicle::create([
            'registration_number' => 'DFT'.random_int(100, 999), 'make' => 'Toyota', 'model' => 'Camry',
            'year' => 2023, 'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 8000,
        ]);
        $draft = Agreement::create([
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'type' => 'private',
            'status' => Agreement::STATUS_DRAFT, 'billing_cycle' => 'weekly', 'rate' => 35000,
            'bond_amount' => 50000, 'start_date' => today(), 'version' => 1,
        ]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$draft->id}/return", [
                'odometer_reading' => 100, 'fuel_level' => ReturnInspection::FUEL_FULL,
                'damage_found' => false, 'needs_workshop' => false, 'deduction_amount' => 0,
            ])
            ->assertForbidden();
    }

    public function test_an_agreement_already_returned_cannot_be_returned_again(): void
    {
        $t = $this->makeTenant('rental-return-twice');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $payload = [
            'odometer_reading' => 10200, 'fuel_level' => ReturnInspection::FUEL_HALF,
            'damage_found' => false, 'needs_workshop' => false, 'deduction_amount' => 0,
        ];

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", $payload)
            ->assertSessionHasNoErrors();

        // Once completed, the policy itself refuses a second return (status is
        // no longer signed/active) — a clean 403 before the DB-unique
        // constraint would ever be reached.
        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", $payload)
            ->assertForbidden();

        app()->instance('current_tenant', $t);
        $this->assertSame(1, ReturnInspection::where('agreement_id', $agreement->id)->count());
    }

    public function test_the_return_inspection_record_is_append_only(): void
    {
        $t = $this->makeTenant('rental-return-immutable');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10200, 'fuel_level' => ReturnInspection::FUEL_HALF,
                'damage_found' => false, 'needs_workshop' => false, 'deduction_amount' => 0,
            ]);

        app()->instance('current_tenant', $t);
        $inspection = ReturnInspection::where('agreement_id', $agreement->id)->firstOrFail();

        $this->expectException(AppendOnlyException::class);
        $inspection->update(['odometer_reading' => 99999]);
    }

    // ── Notification ─────────────────────────────────────────────────────────

    public function test_completing_a_return_notifies_the_customer(): void
    {
        $t = $this->makeTenant('rental-return-notify');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeSignedAgreement($t, ['bond_amount' => 50000]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10200, 'fuel_level' => ReturnInspection::FUEL_HALF,
                'damage_found' => false, 'needs_workshop' => false, 'deduction_amount' => 0,
            ]);

        app()->instance('current_tenant', $t);
        $this->assertSame(1, NotificationLog::where('event_type', 'bond.refunded')->count());
    }

    // ── Tenant isolation ──────────────────────────────────────────────────────

    public function test_staff_cannot_return_a_vehicle_for_another_tenants_agreement(): void
    {
        $t1 = $this->makeTenant('rental-tenant-a');
        $agreement = $this->makeSignedAgreement($t1, ['bond_amount' => 50000]);

        $t2 = $this->makeTenant('rental-tenant-b');
        $otherAdmin = $this->makeAdmin($t2);

        $this->actingAs($otherAdmin, 'tenant')
            ->post("/app/{$t2->slug}/agreements/{$agreement->id}/return", [
                'odometer_reading' => 10200, 'fuel_level' => ReturnInspection::FUEL_HALF,
                'damage_found' => false, 'needs_workshop' => false, 'deduction_amount' => 0,
            ])
            ->assertNotFound();
    }
}
