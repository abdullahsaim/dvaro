<?php

namespace App\Modules\Finance\Events;

use App\Modules\Finance\Models\Expense;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired exclusively from the matching Finance expense action, after its ledger
 * entries are written. Busts the expense / profit report caches
 * (ExpenseReportCacheListener).
 */
class ExpenseUpdated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Expense $expense,
    ) {}
}
