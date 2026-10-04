<?php

namespace Tests\Feature;

use App\Jobs\SendAgreementSigningLinkJob;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementSigningService;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Client feedback #4: "agreement ko customer ki email and whatsapp per send
 * kerny ka option huna chahye so that wo usko review kerky online sign
 * kerskay, aur jab woh sign kerdy to usko signed document email hujaye
 * automatic" — a public, token-based link so the customer can review and
 * sign an agreement without a DVARO login, and an automatic email with a
 * working link to the signed copy once they do.
 *
 * Two routes exercise this: the AUTHENTICATED "send for signing" action
 * (staff triggers it from the agreement page) and the PUBLIC review/sign page
 * (the customer opens the link with no login at all). The same link keeps
 * working after signing — it switches to a read-only "here's your copy" page
 * rather than ever 404ing a link that has already been used once.
 */
class AgreementSigningTest extends TestCase
{
    use DatabaseTransactions;

    private function makeTenant(string $slug, array $settings = []): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug), 'slug' => $slug, 'status' => Tenant::STATUS_ACTIVE,
            'settings' => $settings ?: null,
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

    private function makeStaff(Tenant $tenant): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'Staffer', 'email' => 'staff-'.uniqid().'@'.$tenant->slug.'.test',
            'password' => 'secret123', 'role' => TenantUser::ROLE_STAFF, 'is_active' => true,
        ]);
    }

    private function makeDraftAgreement(Tenant $tenant, array $customerAttrs = [], array $agreementAttrs = []): Agreement
    {
        app()->instance('current_tenant', $tenant);

        $customer = Customer::create(array_merge([
            'name' => 'Jordan Blake', 'email' => 'jordan-'.uniqid().'@test.au', 'phone' => '0400111222',
            'licence_number' => 'WA1234567', 'emergency_contact_name' => 'Kin', 'emergency_contact_phone' => '1',
        ], $customerAttrs));

        $vehicle = Vehicle::create([
            'registration_number' => 'SIGN'.random_int(100, 999), 'make' => 'Toyota', 'model' => 'Camry',
            'year' => 2023, 'status' => Vehicle::STATUS_AVAILABLE, 'daily_rate' => 8000,
        ]);

        return Agreement::create(array_merge([
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
    }

    // ── Sending the link (authenticated side) ───────────────────────────────

    public function test_admin_can_send_the_signing_link_by_email(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-send');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeDraftAgreement($t);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['email']])
            ->assertSessionHasNoErrors();

        Queue::assertPushedOn('notifications', SendAgreementSigningLinkJob::class);
        $this->assertNotNull($agreement->fresh()->signing_sent_at);
    }

    public function test_whatsapp_is_only_offered_when_the_tenant_has_it_enabled(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-wa-off', ['notify_whatsapp_enabled' => false]);
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeDraftAgreement($t);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['whatsapp']])
            ->assertSessionHasErrors('channels.0');

        Queue::assertNothingPushed();
    }

    public function test_whatsapp_works_once_enabled_and_the_customer_has_a_phone(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-wa-on', ['notify_whatsapp_enabled' => true]);
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeDraftAgreement($t, ['phone' => '0411222333']);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['whatsapp']])
            ->assertSessionHasNoErrors();

        Queue::assertPushedOn('notifications', SendAgreementSigningLinkJob::class);
    }

    public function test_a_channel_with_no_contact_method_on_file_is_skipped_not_silently_lost(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-no-contact', ['notify_whatsapp_enabled' => true]);
        $admin = $this->makeAdmin($t);
        // Customer has an email but deliberately no phone (NOT NULL column).
        $agreement = $this->makeDraftAgreement($t, ['phone' => '']);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['email', 'whatsapp']])
            ->assertSessionHasNoErrors();

        // Only the channel that actually had a contact method was dispatched.
        Queue::assertPushed(SendAgreementSigningLinkJob::class, 1);
    }

    public function test_a_signed_agreement_cannot_be_sent_for_signing(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-already');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeDraftAgreement($t, [], ['status' => Agreement::STATUS_SIGNED]);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['email']])
            ->assertStatus(422);

        Queue::assertNothingPushed();
    }

    public function test_sending_issues_a_token_the_public_link_can_use(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-token');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeDraftAgreement($t);

        $this->assertNull($agreement->signing_token);

        $this->actingAs($admin, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['email']]);

        $this->assertNotNull($agreement->fresh()->signing_token);
    }

    public function test_the_signing_token_never_appears_in_the_staff_page_payload(): void
    {
        $t = $this->makeTenant('sign-hidden');
        $admin = $this->makeAdmin($t);
        $agreement = $this->makeDraftAgreement($t);
        app(AgreementSigningService::class)->ensureToken($agreement);

        $response = $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/agreements/{$agreement->id}");

        $response->assertOk();
        $this->assertStringNotContainsString($agreement->fresh()->signing_token, $response->getContent());
    }

    // ── The public review-and-sign page ─────────────────────────────────────

    public function test_the_public_link_renders_the_review_form_for_a_draft(): void
    {
        $t = $this->makeTenant('sign-public-show');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->get("/agreement-sign/{$t->slug}/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Agreement/PublicSign')
                ->where('customerName', 'Jordan Blake')
                ->where('rate', 35000));
    }

    public function test_an_unknown_token_is_unavailable_not_a_crash(): void
    {
        $t = $this->makeTenant('sign-public-bad');

        $this->get("/agreement-sign/{$t->slug}/not-a-real-token")
            ->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('Agreement/PublicSignUnavailable'));
    }

    public function test_a_token_for_a_suspended_tenant_is_unavailable(): void
    {
        $t = $this->makeTenant('sign-suspended');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $t->update(['status' => Tenant::STATUS_SUSPENDED]);

        $this->get("/agreement-sign/{$t->slug}/{$token}")->assertNotFound();
    }

    public function test_a_tokens_tenant_slug_must_match_or_it_is_unavailable(): void
    {
        $t = $this->makeTenant('sign-wrong-slug');
        $other = $this->makeTenant('sign-other-slug');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->get("/agreement-sign/{$other->slug}/{$token}")->assertNotFound();
    }

    // ── Actually signing ─────────────────────────────────────────────────────

    private function fakeSignature(): string
    {
        return 'data:image/png;base64,'.base64_encode('fake-signature-bytes');
    }

    public function test_submitting_a_signature_signs_the_agreement(): void
    {
        $t = $this->makeTenant('sign-submit');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => $this->fakeSignature()])
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Agreement/PublicSignThankYou'));

        $fresh = $agreement->fresh();
        $this->assertSame(Agreement::STATUS_SIGNED, $fresh->status);
        $this->assertNotNull($fresh->signed_at);
    }

    public function test_the_same_link_shows_a_thank_you_page_after_signing_instead_of_404ing(): void
    {
        $t = $this->makeTenant('sign-revisit');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => $this->fakeSignature()]);

        // The customer reopens the same email link later.
        $this->get("/agreement-sign/{$t->slug}/{$token}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Agreement/PublicSignThankYou'));
    }

    public function test_an_already_signed_agreement_cannot_be_signed_again_through_the_link(): void
    {
        $t = $this->makeTenant('sign-double');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => $this->fakeSignature()]);
        $firstSignature = $agreement->fresh()->signature_data;

        // A second tab / resubmission must not overwrite the first signature.
        $second = $this->fakeSignature();
        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => $second])
            ->assertInertia(fn ($page) => $page->component('Agreement/PublicSignThankYou'));

        $this->assertSame($firstSignature, $agreement->fresh()->signature_data);
    }

    public function test_a_malformed_signature_is_rejected(): void
    {
        $t = $this->makeTenant('sign-bad-data');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => 'not-a-data-url'])
            ->assertSessionHasErrors('signature_data');

        $this->assertSame(Agreement::STATUS_DRAFT, $agreement->fresh()->status);
    }

    public function test_signing_through_the_public_link_fires_the_same_notification_as_signing_in_person(): void
    {
        $t = $this->makeTenant('sign-notify');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => $this->fakeSignature()]);

        app()->instance('current_tenant', $t);
        $this->assertSame(1, NotificationLog::where('event_type', 'agreement.signed')->count());
    }

    // ── Downloading the signed copy (public, token-gated) ───────────────────

    public function test_the_pdf_download_is_offered_only_once_it_actually_exists(): void
    {
        Storage::fake('local');
        // Under the sync queue connection (this test suite's default) a
        // dispatched job runs INLINE, so without faking the queue the PDF
        // would already exist by the time sign() returns — exactly what this
        // test needs to NOT happen yet, to prove pdfReady genuinely reflects
        // the file rather than just the agreement's status.
        Queue::fake();
        $t = $this->makeTenant('sign-pdf');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        $this->post("/agreement-sign/{$t->slug}/{$token}", ['signature_data' => $this->fakeSignature()])
            ->assertInertia(fn ($page) => $page->where('pdfReady', false)); // job hasn't run yet

        $this->get("/agreement-sign/{$t->slug}/{$token}/pdf")->assertNotFound();

        // Once the (queued) PDF exists…
        $path = "tenants/{$t->id}/agreements/{$agreement->id}/agreement-v1.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4');
        $agreement->update(['pdf_path' => $path]);

        $this->get("/agreement-sign/{$t->slug}/{$token}")
            ->assertInertia(fn ($page) => $page->where('pdfReady', true));
        $this->get("/agreement-sign/{$t->slug}/{$token}/pdf")->assertOk();
    }

    public function test_the_pdf_cannot_be_downloaded_before_the_agreement_is_signed(): void
    {
        Storage::fake('local');
        $t = $this->makeTenant('sign-pdf-draft');
        $agreement = $this->makeDraftAgreement($t);
        $token = app(AgreementSigningService::class)->ensureToken($agreement);

        // Even if a path were somehow set early, a draft is never downloadable.
        $path = "tenants/{$t->id}/agreements/{$agreement->id}/agreement-v1.pdf";
        Storage::disk('local')->put($path, '%PDF-1.4');
        $agreement->update(['pdf_path' => $path]);

        $this->get("/agreement-sign/{$t->slug}/{$token}/pdf")->assertNotFound();
    }

    // ── Isolation and role checks ────────────────────────────────────────────

    public function test_staff_can_send_but_cannot_rebrand_the_link_across_tenants(): void
    {
        $a = $this->makeTenant('sign-iso-a');
        $staffA = $this->makeStaff($a);
        $agreementA = $this->makeDraftAgreement($a);

        $b = $this->makeTenant('sign-iso-b');
        $adminB = $this->makeAdmin($b);

        // Tenant B's admin cannot act on tenant A's agreement — 404 via
        // TenantScope route-model binding, same as every other module.
        $this->actingAs($adminB, 'tenant')
            ->post("/app/{$b->slug}/agreements/{$agreementA->id}/send-for-signing", ['channels' => ['email']])
            ->assertNotFound();
    }

    public function test_staff_may_send_the_link_like_any_other_tenant_user(): void
    {
        Queue::fake();
        $t = $this->makeTenant('sign-staff-ok');
        $staff = $this->makeStaff($t);
        $agreement = $this->makeDraftAgreement($t);

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$t->slug}/agreements/{$agreement->id}/send-for-signing", ['channels' => ['email']])
            ->assertSessionHasNoErrors();

        Queue::assertPushed(SendAgreementSigningLinkJob::class);
    }
}
