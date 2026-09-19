<?php

namespace App\Modules\Agreement\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Agreement\Http\Requests\AgreementTemplateRequest;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Models\AgreementTemplate;
use App\Modules\Agreement\Services\AgreementTemplateService;
use App\Modules\Agreement\Services\AgreementTermsRenderer;
use App\Modules\SaasCore\Models\TenantUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tenant-side agreement terms templates (Settings → Agreement templates).
 *
 * AUTHORIZATION: AgreementTemplatePolicy via
 * Gate::forUser(auth('tenant')->user()) on every action — tenant_admin writes,
 * everyone reads. {template} is resolved through AgreementTemplate::visibleTo()
 * (own + platform defaults, scope-free but tenant-constrained) because the
 * global TenantScope hides the NULL-tenant platform defaults.
 */
class AgreementTemplateController extends Controller
{
    public function __construct(
        private readonly AgreementTemplateService $templates,
    ) {}

    public function index(): Response
    {
        $user = auth('tenant')->user();
        Gate::forUser($user)->authorize('viewAny', AgreementTemplate::class);

        $tenant = app('current_tenant');

        return Inertia::render('Agreement/Templates/Index', [
            'templates' => AgreementTemplate::query()->orderBy('name')->get(),
            'platformDefaults' => AgreementTemplate::platformDefaults()->active()->orderBy('name')->get(),
            'types' => Agreement::TYPES,
            'states' => AgreementTemplate::STATES,
            'mergeFields' => AgreementTermsRenderer::fields(),
            'sampleValues' => app(AgreementTermsRenderer::class)->sampleValues(),
            'defaultState' => $tenant->settings['default_state'] ?? null,
            'canManage' => $user instanceof TenantUser && $user->role === TenantUser::ROLE_ADMIN,
        ]);
    }

    public function store(AgreementTemplateRequest $request): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', AgreementTemplate::class);

        $this->templates->save(null, $request->validated(), app('current_tenant')->id);

        return back()->with('success', __('common.agreement_template.saved'));
    }

    public function update(AgreementTemplateRequest $request, int $template): RedirectResponse
    {
        $model = $this->find($template);

        Gate::forUser(auth('tenant')->user())->authorize('update', $model);

        $this->templates->save($model, $request->validated(), (int) $model->tenant_id);

        return back()->with('success', __('common.agreement_template.saved'));
    }

    /** Copy a platform default into this tenant's own (editable) library. */
    public function copy(int $template): RedirectResponse
    {
        $model = $this->find($template);

        Gate::forUser(auth('tenant')->user())->authorize('copy', $model);

        $this->templates->copyToTenant($model, app('current_tenant')->id);

        return back()->with('success', __('common.agreement_template.copied'));
    }

    /** The state pre-selected on new agreements. */
    public function updateDefaultState(Request $request): RedirectResponse
    {
        Gate::forUser(auth('tenant')->user())->authorize('create', AgreementTemplate::class);

        $validated = $request->validate([
            'default_state' => ['nullable', Rule::in(AgreementTemplate::STATES)],
        ]);

        $tenant = app('current_tenant');
        $tenant->settings = [...($tenant->settings ?? []), 'default_state' => $validated['default_state'] ?: null];
        $tenant->save();

        return back()->with('success', __('common.agreement_template.state_saved'));
    }

    /**
     * Own template or platform default, else 404 — never another tenant's
     * (visibleTo constrains tenant_id explicitly).
     */
    private function find(int $id): AgreementTemplate
    {
        return AgreementTemplate::visibleTo(app('current_tenant')->id)->findOrFail($id);
    }
}
