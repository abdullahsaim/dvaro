<?php

namespace App\Modules\Agreement\Models;

use App\Scopes\TenantScope;
use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Rental-agreement terms template.
 *
 * ──────────────────────────────────────────────────────────────────────────
 * HYBRID OWNERSHIP — READ BEFORE EDITING:
 *   tenant_id SET  → a rental company's own template. HasTenant/TenantScope
 *                    applies, so ordinary queries only ever see the bound
 *                    tenant's rows (a cross-tenant id is never found).
 *   tenant_id NULL → a PLATFORM DEFAULT, managed by the super admin and
 *                    readable by every tenant as a fallback / copy source.
 *
 * TenantScope hides NULL-tenant rows from tenant queries, so platform defaults
 * MUST be read through the scope-free helpers below (platformDefaults() /
 * scopeVisibleTo) — never with a bare AgreementTemplate::query(). This mirrors
 * the documented scope-free pattern in IntakeFormController.
 * ──────────────────────────────────────────────────────────────────────────
 *
 * body_html is sanitised HTML (AgreementTermsSanitizer) containing
 * {{merge.fields}}. It is NEVER rendered into a signed agreement live: the
 * resolved text is frozen onto agreements.terms_html at creation.
 */
class AgreementTemplate extends Model
{
    use HasTenant;

    /** Australian states/territories a template can be scoped to. */
    public const STATES = ['NSW', 'VIC', 'QLD', 'WA', 'SA', 'TAS', 'ACT', 'NT'];

    protected $fillable = [
        'tenant_id',
        'name',
        'agreement_type',
        'state',
        'body_html',
        'revision',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'revision' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function isPlatformDefault(): bool
    {
        return $this->tenant_id === null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope-free query over the PLATFORM DEFAULTS only (tenant_id NULL).
     * Safe by construction: it can never return another tenant's rows.
     */
    public static function platformDefaults(): Builder
    {
        return static::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereNull('tenant_id');
    }

    /**
     * Scope-free query over everything ONE tenant may read: its own templates
     * plus the platform defaults. tenant_id is constrained explicitly.
     */
    public static function visibleTo(int $tenantId): Builder
    {
        return static::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where(fn (Builder $q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'));
    }
}
