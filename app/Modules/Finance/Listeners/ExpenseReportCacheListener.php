<?php

namespace App\Modules\Finance\Listeners;

use App\Modules\Finance\Events\ExpenseRecorded;
use App\Modules\Finance\Events\ExpenseUpdated;
use App\Modules\Finance\Events\ExpenseVoided;
use App\Modules\Reporting\Services\ReportCacheService;

/**
 * Busts the cached Expenses report and profit-per-vehicle (revenue by vehicle)
 * for the expense's tenant whenever an expense is recorded, edited or voided,
 * so reports reflect it immediately instead of after the 30-minute TTL.
 * Synchronous and cheap; targets the expense's own tenant_id.
 */
class ExpenseReportCacheListener
{
    public function __construct(private readonly ReportCacheService $cache) {}

    public function handle(ExpenseRecorded|ExpenseUpdated|ExpenseVoided $event): void
    {
        $tenantId = (int) $event->expense->tenant_id;

        $this->cache->invalidate(ReportCacheService::TYPE_EXPENSES, $tenantId);
        $this->cache->invalidate(ReportCacheService::TYPE_REVENUE_BY_VEHICLE, $tenantId);
    }
}
