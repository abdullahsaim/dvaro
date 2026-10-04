<?php

namespace App\Modules\Rental\Events;

use App\Modules\Rental\Models\ReturnInspection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once the bond refund figure for a return is settled — exclusively
 * from RentalReturnService::completeReturn(), in the same transaction as the
 * ledger entries it reports. Fired even when the refund is $0 (bond fully
 * withheld) — the customer is still owed an explanation either way.
 */
class BondRefunded
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ReturnInspection $inspection,
    ) {}
}
