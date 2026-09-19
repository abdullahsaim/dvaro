<?php

namespace App\Modules\Agreement\Services;

use App\Modules\Agreement\Models\AgreementTemplate;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

/**
 * Agreement terms templates: selection cascade, sanitised saves, and copying a
 * platform default into a tenant's own library.
 *
 * SELECTION (most specific wins), for a tenant + agreement type + state:
 *   tenant · type · state  →  tenant · type · any state  →  tenant · any · state
 *   →  tenant · any · any  →  then the same four over the PLATFORM DEFAULTS.
 * Only ACTIVE templates are selectable; archived ones stay readable for history.
 */
class AgreementTemplateService extends BaseService
{
    public function __construct(
        private readonly AgreementTermsSanitizer $sanitizer,
        private readonly AgreementTermsRenderer $renderer,
    ) {}

    /** The template that should apply, or null when nothing is configured. */
    public function resolveFor(int $tenantId, string $type, ?string $state): ?AgreementTemplate
    {
        return AgreementTemplate::visibleTo($tenantId)
            ->active()
            ->where(fn (Builder $q) => $q->where('agreement_type', $type)->orWhereNull('agreement_type'))
            ->where(fn (Builder $q) => $q->where('state', $state)->orWhereNull('state'))
            // Specificity: own template first, then type match, then state match.
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN agreement_type IS NULL THEN 1 ELSE 0 END')
            ->orderByRaw('CASE WHEN state IS NULL THEN 1 ELSE 0 END')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Templates a tenant may choose from on the agreement form (own + platform
     * defaults, active only), most specific first.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AgreementTemplate>
     */
    public function selectableFor(int $tenantId): object
    {
        return AgreementTemplate::visibleTo($tenantId)
            ->active()
            ->orderByRaw('CASE WHEN tenant_id IS NULL THEN 1 ELSE 0 END')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'agreement_type', 'state']);
    }

    /**
     * Create or update a template. Sanitises the body, rejects unknown merge
     * fields and empty terms, and bumps the revision on every content change.
     *
     * @param  array{name:string, agreement_type?:?string, state?:?string, body_html:string, is_active?:bool}  $data
     */
    public function save(?AgreementTemplate $template, array $data, ?int $tenantId): AgreementTemplate
    {
        $body = $this->sanitizer->sanitize($data['body_html']);

        if ($this->sanitizer->isEmpty($body)) {
            throw ValidationException::withMessages(['body_html' => __('common.agreement_template.empty')]);
        }

        $unknown = $this->renderer->unknown($body);

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'body_html' => __('common.agreement_template.unknown_field', ['field' => $unknown[0]]),
            ]);
        }

        // Optional keys are absent (not null) when the form omits them.
        $attributes = [
            'name' => trim($data['name']),
            'agreement_type' => ($data['agreement_type'] ?? null) ?: null,
            'state' => ($data['state'] ?? null) ?: null,
            'body_html' => $body,
            'is_active' => $data['is_active'] ?? true,
        ];

        if ($template === null) {
            $created = AgreementTemplate::query()->create([
                ...$attributes,
                'tenant_id' => $tenantId,
                'revision' => 1,
            ]);

            // HasTenant auto-fills tenant_id from the BOUND tenant whenever it
            // is empty — which would quietly turn a PLATFORM DEFAULT into that
            // tenant's template (e.g. a super admin acting while impersonating,
            // or any code path with a tenant bound). Force it back.
            if ($tenantId === null && $created->tenant_id !== null) {
                $created->forceFill(['tenant_id' => null])->save();
            }

            return $created;
        }

        // Revision only advances when the wording actually changes.
        if ($template->body_html !== $body) {
            $attributes['revision'] = $template->revision + 1;
        }

        $template->update($attributes);

        return $template;
    }

    /**
     * Copy a PLATFORM DEFAULT into a tenant's own library so they can edit it.
     * Refuses anything that is not a platform default (a tenant must never copy
     * another tenant's template — visibleTo() already prevents reading one).
     */
    public function copyToTenant(AgreementTemplate $template, int $tenantId): AgreementTemplate
    {
        if (! $template->isPlatformDefault()) {
            throw ValidationException::withMessages(['template' => __('common.agreement_template.not_copyable')]);
        }

        return AgreementTemplate::query()->create([
            'tenant_id' => $tenantId,
            'name' => $template->name,
            'agreement_type' => $template->agreement_type,
            'state' => $template->state,
            'body_html' => $template->body_html,
            'revision' => 1,
            'is_active' => true,
        ]);
    }
}
