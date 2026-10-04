<?php

namespace App\Modules\Rental\Events;

use App\Modules\Agreement\Models\Agreement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a bond/security deposit is collected — exclusively from
 * AgreementService::sign(), alongside the ledger entry that actually records
 * it. Never fired for an agreement with no bond (bond_amount = 0).
 */
class BondCollected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Agreement $agreement,
        public readonly int $amount,
    ) {}
}
