<?php

namespace Tests\Feature;

use App\Modules\Customer\Models\Customer;
use App\Modules\Invoice\Events\PaymentReceived;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\Notification\Services\NotificationMatrix;
use App\Modules\SaasCore\Models\Plan;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * The per-trigger notification matrix: which notifications leave the building,
 * to whom, on which channel. Before this, three global switches decided
 * everything — a company could not keep SMS receipts while silencing SMS
 * invoice notices.
 *
 * The rule under test throughout: an untouched tenant behaves exactly as it did
 * before the matrix existed.
 */
class NotificationMatrixTest extends TestCase
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
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE,
            'plan_id' => $plan->id, 'settings' => $settings ?: null,
        ]);

        app()->instance('current_tenant', $tenant);

        return $tenant;
    }

    private function admin(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Owner', 'email' => 'owner-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => TenantUser::ROLE_ADMIN, 'is_active' => true,
        ]);
    }

    // ── The service ─────────────────────────────────────────────────────────

    public function test_an_untouched_tenant_keeps_the_old_behaviour(): void
    {
        $t = $this->makeTenant('mx-a'); // no settings at all
        $matrix = app(NotificationMatrix::class);

        // Email on by default, SMS/WhatsApp off — exactly the old global rules.
        $this->assertTrue($matrix->allows($t, 'payment.received', NotificationMatrix::EMAIL));
        $this->assertFalse($matrix->allows($t, 'payment.received', NotificationMatrix::SMS));
        $this->assertFalse($matrix->allows($t, 'payment.received', NotificationMatrix::WHATSAPP));
    }

    public function test_a_channel_can_be_silenced_for_one_trigger_only(): void
    {
        $t = $this->makeTenant('mx-b', [
            'notify_sms_enabled' => true,
            'notification_matrix' => ['invoice.generated' => ['sms' => false]],
        ]);
        $matrix = app(NotificationMatrix::class);

        $this->assertFalse($matrix->allows($t, 'invoice.generated', NotificationMatrix::SMS));
        $this->assertTrue($matrix->allows($t, 'payment.received', NotificationMatrix::SMS));
        // Email on that same trigger is untouched.
        $this->assertTrue($matrix->allows($t, 'invoice.generated', NotificationMatrix::EMAIL));
    }

    public function test_the_master_switch_still_wins(): void
    {
        $t = $this->makeTenant('mx-c', [
            'notify_sms_enabled' => false,
            'notification_matrix' => ['payment.received' => ['sms' => true]],
        ]);

        $this->assertFalse(app(NotificationMatrix::class)->allows($t, 'payment.received', NotificationMatrix::SMS));
    }

    public function test_unsupported_channels_and_unknown_triggers(): void
    {
        $t = $this->makeTenant('mx-d', ['notify_sms_enabled' => true]);
        $matrix = app(NotificationMatrix::class);

        // Staff have no phone field, so a staff trigger has no SMS column.
        $this->assertFalse($matrix->allows($t, 'lead.submitted', NotificationMatrix::SMS));
        $this->assertTrue($matrix->allows($t, 'lead.submitted', NotificationMatrix::EMAIL));

        // A trigger with no row (staff invitations) follows the master switch.
        $this->assertTrue($matrix->allows($t, 'staff.invitation', NotificationMatrix::EMAIL));
    }

    public function test_billing_alerts_cannot_be_silenced(): void
    {
        $t = $this->makeTenant('mx-e', [
            'notification_matrix' => ['subscription.payment_failed' => ['email' => false]],
        ]);

        $this->assertTrue(app(NotificationMatrix::class)->allows($t, 'subscription.payment_failed', NotificationMatrix::EMAIL));
    }

    public function test_sanitize_drops_anything_the_form_should_not_control(): void
    {
        $clean = app(NotificationMatrix::class)->sanitize([
            'payment.received' => ['email' => '0', 'sms' => 'true', 'carrier_pigeon' => '1'],
            'lead.submitted' => ['sms' => '1'],                   // unsupported channel
            'subscription.cancelled' => ['email' => false],       // locked
            'made.up' => ['email' => true],                       // unknown trigger
        ]);

        $this->assertSame(['payment.received' => ['email' => false, 'sms' => true]], $clean);
    }

    public function test_the_ui_payload_describes_every_trigger(): void
    {
        $t = $this->makeTenant('mx-f', ['notification_matrix' => ['payment.received' => ['email' => false]]]);

        $rows = collect(app(NotificationMatrix::class)->forUi($t));

        $this->assertCount(count(NotificationMatrix::TRIGGERS), $rows);

        $payment = $rows->firstWhere('key', 'payment.received');
        $this->assertSame('Payment received', $payment['label']); // translated, not hardcoded
        $this->assertFalse($payment['channels']['email']['enabled']);
        $this->assertTrue($payment['channels']['sms']['channel_off']); // SMS master switch off
        $this->assertFalse($payment['locked']);

        $this->assertTrue($rows->firstWhere('key', 'subscription.cancelled')['locked']);
        $this->assertArrayNotHasKey('sms', $rows->firstWhere('key', 'lead.submitted')['channels']);
    }

    // ── End to end ──────────────────────────────────────────────────────────

    public function test_silencing_a_trigger_stops_the_real_notification(): void
    {
        $t = $this->makeTenant('mx-g', [
            'notification_matrix' => ['payment.received' => ['email' => false]],
        ]);

        $this->firePayment($t);

        app()->instance('current_tenant', $t);
        $this->assertSame(0, NotificationLog::where('event_type', 'payment.received')->count());

        // The same event with the row left alone still sends.
        $other = $this->makeTenant('mx-h');
        $this->firePayment($other);

        app()->instance('current_tenant', $other);
        $this->assertSame(1, NotificationLog::where('event_type', 'payment.received')->count());
    }

    // ── The screen ──────────────────────────────────────────────────────────

    public function test_the_settings_screen_renders_and_saves_the_matrix(): void
    {
        $t = $this->makeTenant('mx-i');
        $admin = $this->admin($t);
        $url = "/app/{$t->slug}/notifications/settings";

        $this->actingAs($admin, 'tenant')->get($url)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Notification/Settings')
                ->has('matrix', count(NotificationMatrix::TRIGGERS))
                ->where('channels', ['email', 'sms', 'whatsapp']));

        $this->actingAs($admin, 'tenant')->put($url, [
            'notify_email_enabled' => 1,
            'notify_sms_enabled' => 1,
            'notify_whatsapp_enabled' => 0,
            'fleet_reminders_enabled' => 1,
            'fleet_reminder_days' => 30,
            'fleet_reminder_km' => 1000,
            'matrix' => [
                'payment.received' => ['email' => 1, 'sms' => 1],
                'invoice.generated' => ['email' => 1, 'sms' => 0],
                'subscription.cancelled' => ['email' => 0], // locked — must be ignored
            ],
        ])->assertSessionHasNoErrors();

        $stored = app(TenantSettingsService::class)->get($t->fresh(), 'notification_matrix');

        $this->assertTrue($stored['payment.received']['sms']);
        $this->assertFalse($stored['invoice.generated']['sms']);
        $this->assertArrayNotHasKey('subscription.cancelled', $stored);
    }

    public function test_only_admins_may_change_notification_settings(): void
    {
        $t = $this->makeTenant('mx-j');
        app()->instance('current_tenant', $t);
        $staff = TenantUser::create([
            'name' => 'Staffer', 'email' => 'staff@mx-j.test', 'password' => 'secret123',
            'role' => TenantUser::ROLE_STAFF, 'is_active' => true,
        ]);

        $url = "/app/{$t->slug}/notifications/settings";

        $this->actingAs($staff, 'tenant')->get($url)->assertForbidden();
        $this->actingAs($staff, 'tenant')->put($url, [
            'notify_email_enabled' => 0, 'notify_sms_enabled' => 0, 'notify_whatsapp_enabled' => 0,
            'fleet_reminders_enabled' => 0, 'fleet_reminder_days' => 30, 'fleet_reminder_km' => 1000,
        ])->assertForbidden();
    }

    private function firePayment(Tenant $tenant): void
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create([
            'name' => 'Renter', 'email' => 'renter-'.uniqid().'@test.au', 'phone' => '0400000000',
            'licence_number' => 'L1', 'emergency_contact_name' => 'K', 'emergency_contact_phone' => '1',
        ]);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT,
            'issue_date' => today(), 'due_date' => today()->addDays(7),
            'billing_period_start' => today(), 'billing_period_end' => today()->addMonth(),
            'subtotal' => 10000, 'total' => 10000, 'paid_amount' => 10000,
        ]);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'customer_id' => $customer->id,
            'amount' => 10000,
            'method' => Payment::METHOD_CASH,
            'paid_at' => now(),
        ]);

        event(new PaymentReceived($invoice, $payment));
    }
}
