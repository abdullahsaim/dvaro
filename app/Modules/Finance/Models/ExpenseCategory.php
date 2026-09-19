<?php

namespace App\Modules\Finance\Models;

use App\Traits\HasTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant's expense category. Three client-named DEFAULTS exist for every
 * tenant (system_key daily / government / utilities) — renamable and hideable,
 * never deletable. Tenants add their own (system_key NULL). Categories are
 * hidden, never deleted, so historic expenses keep their category.
 */
class ExpenseCategory extends Model
{
    use HasTenant;

    /** system_key => [default name, sort order]. */
    public const DEFAULTS = [
        'daily' => ['Daily expenses', 1],
        'government' => ['Government fees', 2],
        'utilities' => ['Utilities', 3],
    ];

    protected $fillable = [
        'tenant_id',
        'name',
        'system_key',
        'is_hidden',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_hidden', false);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function isSystem(): bool
    {
        return $this->system_key !== null;
    }
}
