<?php

namespace App\Modules\SuperAdmin\Http\Requests;

/**
 * Validates an update to an existing plan. Same rules as creation.
 *
 * Plans are editable (unlike agreements/ledger) — but deletion is blocked while
 * subscriptions reference them (handled at the route/controller level: there is
 * no destroy route).
 */
class UpdatePlanRequest extends StorePlanRequest
{
    // Identical rules to StorePlanRequest. Slug is derived from the name in the
    // service; the FK-restricted delete protection lives in PlanService::delete
    // (no destroy route is wired regardless).
}
