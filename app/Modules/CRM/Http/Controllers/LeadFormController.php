<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Contracts\CaptchaVerifierInterface;
use App\Http\Controllers\Controller;
use App\Jobs\SendLeadFormLinkJob;
use App\Modules\CRM\Http\Requests\SendLeadFormLinkRequest;
use App\Modules\CRM\Http\Requests\UpdateLeadFormSettingsRequest;
use App\Modules\CRM\Models\Lead;
use App\Modules\CRM\Services\LeadFormService;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Tenant-side management of the PUBLIC lead form (CRM → Lead form): share
 * link, QR code, website embed snippet, settings, token regeneration, and
 * "send the link to someone". Thin: authorize → LeadFormService / job.
 *
 * AUTHORIZATION: viewing / sharing uses LeadPolicy via
 * Gate::forUser(auth('tenant')->user()) (same load-bearing pattern as every
 * tenant controller). Changing settings or regenerating the token is
 * tenant-wide configuration → tenant_admin only (same rule as notification
 * settings).
 */
class LeadFormController extends Controller
{
    public function __construct(
        private readonly LeadFormService $forms,
    ) {}

    public function show(CaptchaVerifierInterface $captcha): Response
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Lead::class);

        $tenant = app('current_tenant');

        return Inertia::render('CRM/LeadForm', [
            'publicUrl' => $this->forms->publicUrl($tenant),
            'embedSnippet' => $this->forms->embedSnippet($tenant),
            'settings' => $this->forms->settings($tenant),
            'isAdmin' => $this->isAdmin(),
            'smsEnabled' => (bool) ($tenant->settings['notify_sms_enabled'] ?? false),
            'captchaConfigured' => $captcha->siteKey() !== null,
            'submissions' => Lead::query()
                ->whereIn('source', [Lead::SOURCE_PUBLIC_FORM, Lead::SOURCE_EMBED])
                ->where('created_at', '>=', now()->subDays(30))
                ->count(),
        ]);
    }

    public function update(UpdateLeadFormSettingsRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $this->forms->updateSettings(
            app('current_tenant'),
            $request->boolean('enabled'),
            $request->input('intro'),
            (array) $request->input('allowed_domains', []),
        );

        return back()->with('success', __('common.crm.lead_form_saved'));
    }

    public function regenerate(): RedirectResponse
    {
        $this->authorizeAdmin();

        $this->forms->regenerate(app('current_tenant'));

        return back()->with('success', __('common.crm.lead_form_regenerated'));
    }

    public function send(SendLeadFormLinkRequest $request): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', Lead::class);

        SendLeadFormLinkJob::dispatch(
            app('current_tenant')->id,
            $request->string('channel')->toString(),
            $request->string('recipient')->toString(),
        );

        return back()->with('success', __('common.crm.lead_form_link_sent'));
    }

    /** Print-ready QR code (SVG download) of the public form link. */
    public function qr(): HttpResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('viewAny', Lead::class);

        $tenant = app('current_tenant');

        return response($this->forms->qrSvg($tenant), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="lead-form-'.$tenant->slug.'.svg"',
        ]);
    }

    private function isAdmin(): bool
    {
        $user = auth('tenant')->user();

        return $user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN;
    }

    private function authorizeAdmin(): void
    {
        abort_unless($this->isAdmin(), 403);
    }
}
