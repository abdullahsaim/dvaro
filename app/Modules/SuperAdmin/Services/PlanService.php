<?php

namespace App\Modules\SuperAdmin\Services;

use App\Modules\SaasCore\Models\Plan;
use App\Modules\SuperAdmin\Models\PlatformActivityLog;
use App\Services\PlatformActivityLogger;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Create / update / toggle plans for the super admin panel.
 *
 * Plans ARE editable (unlike agreements and the ledger). Deletion, however, is
 * blocked while any subscription references the plan — the plans→subscriptions
 * FK is restrictOnDelete, so a hard delete would fail at the DB anyway; we guard
 * earlier with a clear error. (No destroy route is wired regardless.)
 */
class PlanService
{
    public function __construct(
        private readonly PlatformActivityLogger $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $data  validated plan attributes
     */
    public function create(array $data): Plan
    {
        $data['slug'] = $this->uniqueSlug($data['name']);
        $data['modules'] = array_values($data['modules'] ?? []);
        $data['limits'] = $data['limits'] ?? [];

        $plan = Plan::create($data);

        $this->activity->log(
            'plan.created',
            PlatformActivityLog::SUBJECT_PLAN,
            $plan->id,
            $plan->name,
            new: ['price_monthly' => $plan->price_monthly, 'is_active' => $plan->is_active],
        );

        return $plan;
    }

    /**
     * @param  array<string, mixed>  $data  validated plan attributes
     */
    public function update(Plan $plan, array $data): Plan
    {
        // Re-derive the slug only when the name changed, ignoring the plan's own
        // current slug for the uniqueness check.
        if ($data['name'] !== $plan->name) {
            $data['slug'] = $this->uniqueSlug($data['name'], $plan->id);
        }

        $data['modules'] = array_values($data['modules'] ?? []);
        $data['limits'] = $data['limits'] ?? [];

        $before = $plan->only(['name', 'price_monthly', 'price_annual', 'is_active']);
        $plan->update($data);

        $this->activity->log(
            'plan.updated',
            PlatformActivityLog::SUBJECT_PLAN,
            $plan->id,
            $plan->name,
            old: $before,
            new: $plan->only(['name', 'price_monthly', 'price_annual', 'is_active']),
        );

        return $plan;
    }

    /**
     * Flip a plan's active state.
     */
    public function toggle(Plan $plan): Plan
    {
        $before = $plan->is_active;
        $plan->update(['is_active' => ! $plan->is_active]);

        $this->activity->log(
            'plan.toggled',
            PlatformActivityLog::SUBJECT_PLAN,
            $plan->id,
            $plan->name,
            old: ['is_active' => $before],
            new: ['is_active' => $plan->is_active],
        );

        return $plan;
    }

    /**
     * Hard-block deletion of a plan that still has subscriptions.
     */
    public function delete(Plan $plan): void
    {
        if ($plan->subscriptions()->exists()) {
            throw new RuntimeException('Cannot delete a plan that has subscriptions.');
        }

        $plan->delete();
    }

    /**
     * A URL-safe slug unique across plans (optionally ignoring one plan id).
     */
    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'plan';
        $slug = $base;
        $i = 2;

        while (Plan::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()
        ) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}
