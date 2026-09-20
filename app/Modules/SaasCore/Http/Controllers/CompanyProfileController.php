<?php

namespace App\Modules\SaasCore\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\Models\AgreementTemplate;
use App\Modules\SaasCore\Http\Requests\CompanyProfileRequest;
use App\Modules\SaasCore\Models\AuditLog;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\SaasCore\Services\TenantSettingsService;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings → Company profile: the details that appear on invoices, agreements
 * and emails, plus the logo and brand colour.
 *
 * The LOGO is a PUBLIC asset (it is printed on customer-facing documents), so
 * it lives on the `public` disk alongside QR codes and CMS images — not on the
 * private disk used for licences and receipts.
 *
 * Admin-only writes; everyone may read (staff need to see the company details).
 */
class CompanyProfileController extends Controller
{
    public const LOGO_MAX_KB = 2048;

    public function __construct(
        private readonly TenantSettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function show(): Response
    {
        $tenant = app('current_tenant');
        $all = $this->settings->all($tenant);

        return Inertia::render('Settings/CompanyProfile', [
            'company' => [
                'name' => $tenant->name,
                'legal_name' => $all['legal_name'],
                'abn' => $all['abn'],
                'phone' => $all['phone'],
                'email' => $all['email'],
                'website' => $all['website'],
                'address' => $all['address'],
                'brand_colour' => $all['brand_colour'],
                'default_state' => $all['default_state'],
                'logo_url' => filled($all['logo_path']) ? Storage::disk('public')->url($all['logo_path']) : null,
            ],
            'states' => AgreementTemplate::STATES,
            'canManage' => $this->isAdmin(),
            'logoMaxKb' => self::LOGO_MAX_KB,
        ]);
    }

    public function update(CompanyProfileRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenant = app('current_tenant');
        $data = $request->validated();

        // The trading name lives on the tenant row (it is the workspace name);
        // everything else is a setting.
        if (($data['name'] ?? null) !== null && $data['name'] !== $tenant->name) {
            $old = $tenant->name;
            $tenant->name = $data['name'];
            $tenant->save();

            $this->audit->log(
                action: 'settings.company_profile.renamed',
                subjectType: AuditLog::SUBJECT_SETTINGS,
                subjectId: $tenant->id,
                subjectLabel: 'company_profile',
                old: ['name' => $old],
                new: ['name' => $tenant->name],
            );
        }

        $this->settings->update($tenant, [
            'legal_name' => $data['legal_name'] ?? null,
            'abn' => $data['abn'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'address' => $data['address'] ?? null,
            'brand_colour' => $data['brand_colour'] ?? TenantSettingsService::DEFAULTS['brand_colour'],
            'default_state' => $data['default_state'] ?? null,
        ], 'company_profile');

        return back()->with('success', __('common.settings.company_saved'));
    }

    /** Replace the logo. PUBLIC disk; the previous file is deleted. */
    public function updateLogo(Request $request): RedirectResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'logo' => ['required', 'file', 'mimes:png,jpg,jpeg,svg,webp', 'max:'.self::LOGO_MAX_KB],
        ]);

        $tenant = app('current_tenant');
        $previous = $this->settings->get($tenant, 'logo_path');

        $path = $request->file('logo')->storeAs(
            "tenants/{$tenant->id}/branding",
            'logo-'.Str::uuid().'.'.strtolower($request->file('logo')->getClientOriginalExtension()),
            'public',
        );

        $this->settings->update($tenant, ['logo_path' => $path], 'company_profile');

        if (filled($previous) && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('success', __('common.settings.logo_saved'));
    }

    public function removeLogo(): RedirectResponse
    {
        $this->authorizeAdmin();

        $tenant = app('current_tenant');
        $previous = $this->settings->get($tenant, 'logo_path');

        $this->settings->update($tenant, ['logo_path' => null], 'company_profile');

        if (filled($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('success', __('common.settings.logo_removed'));
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
