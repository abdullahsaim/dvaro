<?php

namespace Tests\Feature;

use App\Modules\Customer\Events\CustomerPortalInvitationSent;
use App\Modules\Customer\Actions\InviteCustomerToPortalAction;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPortalInvitation;
use App\Modules\Customer\Models\CustomerUser;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Security-critical: the customer portal is the FIFTH guard and must stay
 * isolated per tenant, separate from the tenant guard, and per-customer (a
 * logged-in customer may only ever see their own data). Exercises the real HTTP
 * stack (ResolveTenantForCustomer + auth:customer + the guest-redirect branch +
 * the public invitation-acceptance flow).
 *
 * DatabaseTransactions (rolls back) — NOT RefreshDatabase (never migrate:fresh).
 */
class CustomerPortalTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug): Tenant
    {
        return Tenant::create([
            'name' => ucfirst($slug),
            'slug' => $slug,
            'status' => Tenant::STATUS_ACTIVE,
        ]);
    }

    private function forgetGuards(): void
    {
        $this->app['auth']->forgetGuards();
    }

    private function makeCustomer(Tenant $tenant, string $email): Customer
    {
        app()->instance('current_tenant', $tenant);

        return Customer::create([
            'name' => 'Renter '.$email,
            'email' => $email,
            'phone' => '0400000000',
            'licence_number' => 'LIC-'.uniqid(),
            'emergency_contact_name' => 'Kin',
            'emergency_contact_phone' => '0411111111',
        ]);
    }

    private function makeCustomerUser(Tenant $tenant, Customer $customer, string $password = 'secret123'): CustomerUser
    {
        app()->instance('current_tenant', $tenant);

        return CustomerUser::create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'password' => $password,
        ]);
    }

    private function makeInvitation(Tenant $tenant, Customer $customer, string $token, ?string $expiresAt = null): CustomerPortalInvitation
    {
        app()->instance('current_tenant', $tenant);

        return CustomerPortalInvitation::create([
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'token' => $token,
            'expires_at' => $expiresAt ?? now()->addDays(7),
        ]);
    }

    private function makeInvoice(Tenant $tenant, Customer $customer, int $total = 10000, int $paid = 0): Invoice
    {
        app()->instance('current_tenant', $tenant);

        return Invoice::create([
            'customer_id' => $customer->id,
            'type' => Invoice::TYPE_MANUAL,
            'status' => Invoice::STATUS_SENT,
            'billing_period_start' => now()->toDateString(),
            'billing_period_end' => now()->addMonth()->toDateString(),
            'due_date' => now()->addWeek()->toDateString(),
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => $paid,
        ]);
    }

    public function test_inviting_a_customer_creates_an_invitation_and_fires_the_event(): void
    {
        Event::fake([CustomerPortalInvitationSent::class]);

        $t = $this->makeTenant('co-a');
        $customer = $this->makeCustomer($t, 'renter@co.test');

        $invitation = app(InviteCustomerToPortalAction::class)->execute($customer);

        $this->assertNotNull($invitation->token);
        $this->assertTrue($invitation->expires_at->isFuture());
        $this->assertNull($invitation->accepted_at);
        Event::assertDispatched(CustomerPortalInvitationSent::class);
    }

    public function test_accepting_an_invitation_creates_a_user_and_logs_in(): void
    {
        $t = $this->makeTenant('co-a');
        $customer = $this->makeCustomer($t, 'renter@co.test');
        $this->makeInvitation($t, $customer, 'tok-accept');

        $this->forgetGuards();
        $this->post('/portal/co-a/invite/tok-accept', [
            'password' => 'newpass123',
            'password_confirmation' => 'newpass123',
        ])->assertRedirect('/portal/co-a/dashboard');

        $this->assertAuthenticated('customer');

        app()->instance('current_tenant', $t);
        $this->assertSame(1, CustomerUser::where('customer_id', $customer->id)->count());
        $this->assertNotNull(CustomerPortalInvitation::where('token', 'tok-accept')->first()->accepted_at);
    }

    public function test_invitation_token_from_another_tenant_is_rejected(): void
    {
        // REQUIREMENT: token lookup matches on token AND explicit tenant_id.
        $a = $this->makeTenant('co-a');
        $this->makeTenant('co-b');
        $customer = $this->makeCustomer($a, 'renter@co.test');
        $this->makeInvitation($a, $customer, 'tok-xtenant');

        // Tenant A's token must NOT resolve on tenant B's portal URL.
        $this->get('/portal/co-b/invite/tok-xtenant')->assertNotFound();
    }

    public function test_expired_invitation_is_gone(): void
    {
        $t = $this->makeTenant('co-a');
        $customer = $this->makeCustomer($t, 'renter@co.test');
        $this->makeInvitation($t, $customer, 'tok-expired', now()->subDay()->toDateTimeString());

        $this->get('/portal/co-a/invite/tok-expired')->assertStatus(410);
    }

    public function test_customer_can_log_in(): void
    {
        $t = $this->makeTenant('co-a');
        $customer = $this->makeCustomer($t, 'renter@co.test');
        $this->makeCustomerUser($t, $customer);

        $this->forgetGuards();
        $this->post('/portal/co-a/login', [
            'email' => 'renter@co.test',
            'password' => 'secret123',
        ])->assertRedirect('/portal/co-a/dashboard');

        $this->assertAuthenticated('customer');
    }

    public function test_customer_credentials_are_isolated_per_tenant(): void
    {
        $a = $this->makeTenant('co-a');
        $b = $this->makeTenant('co-b');
        $ca = $this->makeCustomer($a, 'renter@co.test');
        $cb = $this->makeCustomer($b, 'renter@co.test');
        $this->makeCustomerUser($a, $ca, 'passA');
        $this->makeCustomerUser($b, $cb, 'passB');

        // Tenant A's password must not authenticate against tenant B.
        $this->forgetGuards();
        $this->post('/portal/co-b/login', [
            'email' => 'renter@co.test',
            'password' => 'passA',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('customer');
    }

    public function test_customer_sees_only_their_own_invoices(): void
    {
        $t = $this->makeTenant('co-a');
        $alice = $this->makeCustomer($t, 'alice@co.test');
        $bob = $this->makeCustomer($t, 'bob@co.test');
        $this->makeCustomerUser($t, $alice);
        $aliceInvoice = $this->makeInvoice($t, $alice);
        $bobInvoice = $this->makeInvoice($t, $bob);

        $this->forgetGuards();
        $this->post('/portal/co-a/login', [
            'email' => 'alice@co.test',
            'password' => 'secret123',
        ]);

        // Alice sees her own invoice…
        $this->forgetGuards();
        $this->get("/portal/co-a/invoices/{$aliceInvoice->id}")->assertOk();

        // …but NOT Bob's (same tenant, different customer) — policy denies (403).
        $this->forgetGuards();
        $this->get("/portal/co-a/invoices/{$bobInvoice->id}")->assertForbidden();
    }

    public function test_guest_is_redirected_to_the_customer_login(): void
    {
        $this->makeTenant('co-a');

        // Validates redirectGuestsTo's portal branch + the priority-list pin
        // (ResolveTenantForCustomer must bind the tenant before auth:customer).
        $this->get('/portal/co-a/dashboard')->assertRedirect('/portal/co-a/login');
    }

    public function test_customer_cannot_access_tenant_admin_routes(): void
    {
        $t = $this->makeTenant('co-a');
        $customer = $this->makeCustomer($t, 'renter@co.test');
        $this->makeCustomerUser($t, $customer);

        $this->forgetGuards();
        $this->post('/portal/co-a/login', [
            'email' => 'renter@co.test',
            'password' => 'secret123',
        ]);

        // The customer guard must NOT satisfy auth:tenant — bounced to tenant login.
        $this->forgetGuards();
        $this->get('/app/co-a/customers')->assertRedirect('/app/co-a/login');
    }

    public function test_customer_can_make_a_payment(): void
    {
        $t = $this->makeTenant('co-a');
        $customer = $this->makeCustomer($t, 'renter@co.test');
        $this->makeCustomerUser($t, $customer);
        $invoice = $this->makeInvoice($t, $customer, total: 10000, paid: 0);

        $this->forgetGuards();
        $this->post('/portal/co-a/login', [
            'email' => 'renter@co.test',
            'password' => 'secret123',
        ]);

        $this->forgetGuards();
        $this->post("/portal/co-a/invoices/{$invoice->id}/pay", [
            'amount' => 10000,
            'method' => 'cash',
        ])->assertRedirect("/portal/co-a/invoices/{$invoice->id}");

        app()->instance('current_tenant', $t);
        $fresh = $invoice->fresh();
        $this->assertSame(10000, $fresh->paid_amount);
        $this->assertSame(Invoice::STATUS_PAID, $fresh->status);
        $this->assertSame(1, Payment::where('invoice_id', $invoice->id)->count());
    }
}
