<?php

namespace App\Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\Http\Requests\AgreementTemplateRequest;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Models\AgreementTemplate;
use App\Modules\Agreement\Services\AgreementTemplateService;
use App\Modules\Agreement\Services\AgreementTermsRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * PLATFORM DEFAULT agreement terms (super admin). These are the fallback
 * wording every tenant inherits until they write or copy their own, so this
 * controller only ever touches templates with tenant_id NULL
 * (AgreementTemplate::platformDefaults() — scope-free but NULL-constrained,
 * so a tenant's template can never be edited from here).
 *
 * AUTHORIZATION: the contentAccess gate (platform_owner or content_manager) via
 * Gate::forUser(auth('superadmin')->user()) — never $this->authorize().
 */
class AgreementTemplateController extends Controller
{
    public function __construct(
        private readonly AgreementTemplateService $templates,
    ) {}

    public function index(): Response
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        return Inertia::render('SuperAdmin/AgreementTemplates/Index', [
            'templates' => AgreementTemplate::platformDefaults()->orderBy('name')->get(),
            'types' => Agreement::TYPES,
            'states' => AgreementTemplate::STATES,
            'mergeFields' => AgreementTermsRenderer::fields(),
            'sampleValues' => app(AgreementTermsRenderer::class)->sampleValues(),
        ]);
    }

    public function store(AgreementTemplateRequest $request): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $this->templates->save(null, $request->validated(), null);

        return back()->with('success', __('common.agreement_template.saved'));
    }

    public function update(AgreementTemplateRequest $request, int $template): RedirectResponse
    {
        Gate::forUser(auth('superadmin')->user())->authorize('contentAccess');

        $this->templates->save($this->find($template), $request->validated(), null);

        return back()->with('success', __('common.agreement_template.saved'));
    }

    /** Platform defaults only — a tenant-owned id 404s. */
    private function find(int $id): AgreementTemplate
    {
        return AgreementTemplate::platformDefaults()->findOrFail($id);
    }
}
