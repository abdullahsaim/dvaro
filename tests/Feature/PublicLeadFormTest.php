<?php

namespace Tests\Feature;

use App\Contracts\CaptchaVerifierInterface;
use App\Jobs\SendLeadFormLinkJob;
use App\Modules\CRM\Events\LeadSubmitted;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Services\LeadFormService;
use App\Modules\Notification\Models\NotificationLog;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Services\Captcha\GoogleRecaptchaVerifier;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Client feedback #8 — the tenant's PUBLIC lead form (share link / QR / website
 * embed): token gating, submission pipeline (validation → honeypot → timing →
 * reCAPTCHA → capture), rate limits, framing headers, tenant-side management,
 * and the per-lead intake blank-email regression.
 *
 * DatabaseTransactions (rolls back) — never RefreshDatabase / migrate:fresh.
 */
class PublicLeadFormTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fakeCaptcha(CaptchaVerifierInterface::RESULT_PASSED);
    }

    private function fakeCaptcha(string $result, ?string $siteKey = 'site-key'): void
    {
        $this->app->instance(CaptchaVerifierInterface::class, new class($result, $siteKey) implements CaptchaVerifierInterface {
            public function __construct(private string $result, private ?string $key) {}

            public function verify(?string $token, ?string $ip = null): string
            {
                return $this->result;
            }

            public function siteKey(): ?string
            {
                return $this->key;
            }
        });
    }

    private function makeTenant(string $slug, array $settings = [], string $status = Tenant::STATUS_ACTIVE): Tenant
    {
        $tenant = Tenant::create([
            'name' => ucfirst($slug).' Rentals',
            'slug' => $slug,
            'status' => $status,
            'settings' => $settings ?: null,
        ]);

        app(LeadFormService::class)->ensureToken($tenant);

        return $tenant->fresh();
    }

    private function makeUser(Tenant $tenant, string $role = TenantUser::ROLE_ADMIN): TenantUser
    {
        app()->instance('current_tenant', $tenant);

        return TenantUser::create([
            'name' => 'User',
            'email' => $role.'@'.$tenant->slug.'.test',
            'password' => 'secret123',
            'role' => $role,
        ]);
    }

    private function url(Tenant $tenant, string $suffix = ''): string
    {
        return "/lead/{$tenant->slug}/{$tenant->lead_form_token}{$suffix}";
    }

    /** A valid payload with a start token old enough to pass the timing check. */
    private function payload(array $overrides = []): array
    {
        $started = app(LeadFormService::class)->issueStartToken();
        $this->travel(10)->seconds();

        return array_merge([
            'name' => 'Jordan Blake',
            'phone' => '0412 345 678',
            'email' => 'jordan@example.com',
            'rental_start_date' => today()->addDays(7)->toDateString(),
            'rental_duration' => '3 weeks',
            'notes' => 'Need a ute.',
            'started' => $started,
            'g-recaptcha-response' => 'token',
        ], $overrides);
    }

    private function leadsFor(Tenant $tenant)
    {
        app()->instance('current_tenant', $tenant);

        return Lead::query()->get();
    }

    // ── Access / token gating ───────────────────────────────────────────────

    public function test_form_renders_for_a_valid_token_and_404s_otherwise(): void
    {
        $t = $this->makeTenant('lf-a', ['lead_form_intro' => 'Hi there']);

        $this->get($this->url($t))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CRM/PublicLeadForm')
                ->where('tenantName', 'Lf-a Rentals')
                ->where('intro', 'Hi there')
                ->where('siteKey', 'site-key')
                ->where('embedded', false));

        $this->get("/lead/lf-a/wrong-token")->assertNotFound()
            ->assertInertia(fn ($page) => $page->component('CRM/PublicLeadFormUnavailable'));
    }

    public function test_disabled_form_or_suspended_tenant_is_unavailable(): void
    {
        $off = $this->makeTenant('lf-b', ['lead_form_enabled' => false]);
        $this->get($this->url($off))->assertNotFound();
        $this->post($this->url($off), $this->payload())->assertNotFound();

        $suspended = $this->makeTenant('lf-c', [], Tenant::STATUS_SUSPENDED);
        $this->get($this->url($suspended))->assertNotFound();

        $this->assertCount(0, $this->leadsFor($off));
    }

    public function test_regenerating_the_token_kills_the_old_link(): void
    {
        $t = $this->makeTenant('lf-d');
        $old = $this->url($t);

        app(LeadFormService::class)->regenerate($t);
        $t->refresh();

        $this->get($old)->assertNotFound();
        $this->get($this->url($t))->assertOk();
    }

    public function test_another_tenants_token_does_not_open_this_tenants_form(): void
    {
        $a = $this->makeTenant('lf-e1');
        $b = $this->makeTenant('lf-e2');

        $this->get("/lead/{$a->slug}/{$b->lead_form_token}")->assertNotFound();
        $this->post("/lead/{$a->slug}/{$b->lead_form_token}", $this->payload())->assertNotFound();

        $this->assertCount(0, $this->leadsFor($a));
        $this->assertCount(0, $this->leadsFor($b));
    }

    // ── Submission pipeline ─────────────────────────────────────────────────

    public function test_valid_submission_creates_a_submitted_lead_and_notifies(): void
    {
        Event::fake([LeadSubmitted::class]);
        $t = $this->makeTenant('lf-f');

        $this->post($this->url($t), $this->payload())
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('CRM/PublicLeadFormSuccess'));

        $lead = $this->leadsFor($t)->sole();
        $this->assertSame('Jordan Blake', $lead->name);
        $this->assertSame('jordan@example.com', $lead->email);
        $this->assertSame(Lead::STATUS_NEW, $lead->status);
        $this->assertSame(Lead::SOURCE_PUBLIC_FORM, $lead->source);
        $this->assertSame(Lead::CAPTCHA_PASSED, $lead->captcha_status);
        $this->assertNotNull($lead->submitted_at);
        $this->assertNull($lead->created_by);
        $this->assertNull($lead->referrer_url);

        Event::assertDispatched(LeadSubmitted::class, fn ($e) => $e->lead->is($lead));
    }

    public function test_embedded_submission_records_source_and_safe_referrer(): void
    {
        $t = $this->makeTenant('lf-g');

        $this->post($this->url($t), $this->payload(['embedded' => '1', 'ref' => 'https://acme.com.au/hire']))->assertOk();
        $this->post($this->url($t), $this->payload(['embedded' => '1', 'ref' => 'javascript:alert(1)', 'email' => 'x@example.com']))->assertOk();

        $leads = $this->leadsFor($t)->sortBy('id')->values();
        $this->assertSame([Lead::SOURCE_EMBED, Lead::SOURCE_EMBED], $leads->pluck('source')->all());
        $this->assertSame('https://acme.com.au/hire', $leads[0]->referrer_url);
        $this->assertNull($leads[1]->referrer_url);
    }

    public function test_validation_errors_are_rendered_back_without_a_session(): void
    {
        $t = $this->makeTenant('lf-h');

        $this->post($this->url($t), $this->payload(['email' => 'not-an-email', 'name' => '']))
            ->assertStatus(422)
            ->assertInertia(fn ($page) => $page
                ->component('CRM/PublicLeadForm')
                ->has('errors.email')
                ->has('errors.name'));

        $this->assertCount(0, $this->leadsFor($t));
    }

    public function test_failed_captcha_is_rejected(): void
    {
        $this->fakeCaptcha(CaptchaVerifierInterface::RESULT_FAILED);
        $t = $this->makeTenant('lf-i');

        $this->post($this->url($t), $this->payload())
            ->assertStatus(422)
            ->assertInertia(fn ($page) => $page->has('errors.captcha'));

        $this->assertCount(0, $this->leadsFor($t));
    }

    public function test_unavailable_captcha_accepts_but_flags_unverified(): void
    {
        $this->fakeCaptcha(CaptchaVerifierInterface::RESULT_UNAVAILABLE, null);
        $t = $this->makeTenant('lf-j');

        $this->get($this->url($t))->assertInertia(fn ($page) => $page->where('siteKey', null));
        $this->post($this->url($t), $this->payload(['g-recaptcha-response' => '']))->assertOk();

        $this->assertSame(Lead::CAPTCHA_UNVERIFIED, $this->leadsFor($t)->sole()->captcha_status);
    }

    public function test_honeypot_gets_a_normal_thank_you_but_saves_nothing(): void
    {
        $t = $this->makeTenant('lf-k');

        $this->post($this->url($t), $this->payload(['company_website' => 'http://spam.example']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('CRM/PublicLeadFormSuccess'));

        $this->assertCount(0, $this->leadsFor($t));
    }

    public function test_too_fast_or_forged_timing_is_rejected(): void
    {
        $t = $this->makeTenant('lf-l');

        // Too fast: token issued "now", no time passes.
        $fast = array_merge($this->payload(), ['started' => app(LeadFormService::class)->issueStartToken()]);
        $this->post($this->url($t), $fast)->assertStatus(422)
            ->assertInertia(fn ($page) => $page->has('errors.form'));

        // Forged / missing token.
        $this->post($this->url($t), $this->payload(['started' => 'forged']))->assertStatus(422);
        $this->post($this->url($t), $this->payload(['started' => null]))->assertStatus(422);

        $this->assertCount(0, $this->leadsFor($t));
    }

    public function test_submissions_are_rate_limited_per_ip(): void
    {
        $t = $this->makeTenant('lf-m');
        RateLimiter::clear("lead-form:{$t->slug}:127.0.0.1");

        for ($i = 0; $i < 10; $i++) {
            $this->post($this->url($t), ['name' => ''])->assertStatus(422);
        }

        $this->post($this->url($t), $this->payload())
            ->assertStatus(429)
            ->assertInertia(fn ($page) => $page
                ->component('CRM/PublicLeadFormUnavailable')
                ->where('reason', 'rate_limited'));

        $this->assertCount(0, $this->leadsFor($t));
    }

    // ── Framing headers ─────────────────────────────────────────────────────

    public function test_normal_pages_cannot_be_framed(): void
    {
        $t = $this->makeTenant('lf-n');

        foreach (['/', "/app/{$t->slug}/login", $this->url($t)] as $url) {
            $response = $this->get($url);
            $this->assertSame("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'), $url);
            $this->assertSame('SAMEORIGIN', $response->headers->get('X-Frame-Options'), $url);
        }
    }

    public function test_embed_can_be_framed_by_any_site_or_only_allowed_domains(): void
    {
        $any = $this->makeTenant('lf-o1');
        $open = $this->get($this->url($any, '/embed'))->assertOk();
        $this->assertSame('frame-ancestors *', $open->headers->get('Content-Security-Policy'));
        $this->assertFalse($open->headers->has('X-Frame-Options'));
        $open->assertInertia(fn ($page) => $page->where('embedded', true));

        $locked = $this->makeTenant('lf-o2', ['lead_form_allowed_domains' => ['https://acme.com.au']]);
        $this->assertSame(
            "frame-ancestors 'self' https://acme.com.au",
            $this->get($this->url($locked, '/embed'))->headers->get('Content-Security-Policy'),
        );
    }

    // ── Tenant-side management ──────────────────────────────────────────────

    public function test_staff_can_view_the_lead_form_page_but_only_admin_changes_settings(): void
    {
        $t = $this->makeTenant('lf-p');
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')->get("/app/{$t->slug}/leads/form")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('CRM/LeadForm')
                ->where('isAdmin', false)
                ->where('publicUrl', url($this->url($t))));

        $settings = ['enabled' => true, 'intro' => 'Hello', 'allowed_domains' => ['www.Acme.com.au/contact', 'http://other.com:8080']];

        $this->actingAs($staff, 'tenant')->put("/app/{$t->slug}/leads/form", $settings)->assertForbidden();
        $this->actingAs($admin, 'tenant')->put("/app/{$t->slug}/leads/form", $settings)->assertSessionHasNoErrors();

        $this->assertSame(
            ['https://www.acme.com.au', 'http://other.com:8080'],
            $t->fresh()->settings['lead_form_allowed_domains'],
        );

        $this->actingAs($admin, 'tenant')
            ->put("/app/{$t->slug}/leads/form", ['enabled' => true, 'allowed_domains' => ['not a domain!']])
            ->assertSessionHasErrors('allowed_domains.0');
    }

    public function test_only_admin_can_regenerate(): void
    {
        $t = $this->makeTenant('lf-q');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);
        $admin = $this->makeUser($t, TenantUser::ROLE_ADMIN);
        $before = $t->lead_form_token;

        $this->actingAs($staff, 'tenant')->post("/app/{$t->slug}/leads/form/regenerate")->assertForbidden();
        $this->assertSame($before, $t->fresh()->lead_form_token);

        $this->actingAs($admin, 'tenant')->post("/app/{$t->slug}/leads/form/regenerate")->assertRedirect();
        $this->assertNotSame($before, $t->fresh()->lead_form_token);
    }

    public function test_send_link_is_queued_and_sms_requires_sms_enabled(): void
    {
        Queue::fake();
        $t = $this->makeTenant('lf-r');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$t->slug}/leads/form/send", ['channel' => 'email', 'recipient' => 'cust@example.com'])
            ->assertSessionHasNoErrors();

        Queue::assertPushedOn('notifications', SendLeadFormLinkJob::class,
            fn ($job) => $job->tenantId === $t->id && $job->channel === 'email' && $job->recipient === 'cust@example.com');

        $this->actingAs($staff, 'tenant')
            ->post("/app/{$t->slug}/leads/form/send", ['channel' => 'sms', 'recipient' => '0412345678'])
            ->assertSessionHasErrors('channel');
    }

    public function test_send_link_job_emails_the_current_public_link(): void
    {
        $t = $this->makeTenant('lf-s');

        app()->forgetInstance('current_tenant');
        app()->call([new SendLeadFormLinkJob($t->id, 'email', 'cust@example.com'), 'handle']);

        app()->instance('current_tenant', $t);
        $log = NotificationLog::where('event_type', SendLeadFormLinkJob::EVENT_TYPE)->sole();
        $this->assertSame('cust@example.com', $log->recipient);
        $this->assertStringContainsString($t->lead_form_token, $log->body);
    }

    public function test_qr_download_is_an_svg_of_the_public_link(): void
    {
        $t = $this->makeTenant('lf-t');
        $staff = $this->makeUser($t, TenantUser::ROLE_STAFF);

        $response = $this->actingAs($staff, 'tenant')->get("/app/{$t->slug}/leads/form/qr")->assertOk();

        $this->assertSame('image/svg+xml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('<svg', $response->getContent());
    }

    public function test_leads_list_filters_by_source(): void
    {
        $t = $this->makeTenant('lf-u');
        $admin = $this->makeUser($t);
        $this->post($this->url($t), $this->payload())->assertOk();

        app()->instance('current_tenant', $t);
        Lead::create(['name' => 'Staff lead', 'email' => 's@x.test', 'phone' => '1', 'token' => (string) Str::uuid()]);

        $this->actingAs($admin, 'tenant')->get("/app/{$t->slug}/leads?source=public_form")
            ->assertInertia(fn ($page) => $page
                ->has('leads.data', 1)
                ->where('leads.data.0.source', 'public_form')
                ->where('filters.source', 'public_form'));
    }

    // ── reCAPTCHA verifier (Google) ─────────────────────────────────────────

    public function test_google_verifier_maps_responses(): void
    {
        $verifier = new GoogleRecaptchaVerifier('secret', 'site');

        // One ordered sequence (stacked Http::fake() stubs would shadow each other).
        $responses = [
            fn () => Http::response(['success' => true]),
            fn () => Http::response(['success' => false]),
            fn () => Http::response([], 500),
            fn () => throw new ConnectionException('down'),
        ];
        Http::fake(function () use (&$responses) {
            return array_shift($responses)();
        });

        $this->assertSame('passed', $verifier->verify('tok', '1.2.3.4'));
        $this->assertSame('failed', $verifier->verify('tok'));
        $this->assertSame('failed', $verifier->verify(null)); // no HTTP call
        $this->assertSame('unavailable', $verifier->verify('tok'));
        $this->assertSame('unavailable', $verifier->verify('tok'));

        Http::assertSent(fn ($request) => ($request->data()['secret'] ?? null) === 'secret'
            && ($request->data()['response'] ?? null) === 'tok'
            && ($request->data()['remoteip'] ?? null) === '1.2.3.4');
        // 3 recorded: the null token never calls Google, and a request that
        // throws ConnectionException isn't recorded by the fake.
        Http::assertSentCount(3);
    }

    // ── Regression: per-lead intake blank email (was a 500) ─────────────────

    public function test_per_lead_intake_with_blank_email_keeps_the_stored_email(): void
    {
        $t = $this->makeTenant('lf-v');
        app()->instance('current_tenant', $t);
        $lead = Lead::create(['name' => 'Walk In', 'email' => 'kept@example.com', 'phone' => '1', 'token' => (string) Str::uuid()]);
        app()->forgetInstance('current_tenant');

        $url = URL::signedRoute('crm.intake.submit', ['tenant_slug' => $t->slug, 'token' => $lead->token]);
        $this->post($url, ['name' => 'Walk In', 'phone' => '0400', 'email' => ''])->assertOk();

        app()->instance('current_tenant', $t);
        $fresh = $lead->fresh();
        $this->assertSame('kept@example.com', $fresh->email);
        $this->assertNotNull($fresh->submitted_at);
    }
}
