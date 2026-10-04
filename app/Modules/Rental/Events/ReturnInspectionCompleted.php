<?php

namespace App\Modules\Rental\Events;

use App\Modules\Rental\Models\ReturnInspection;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a vehicle return is recorded — the end of a rental's lifecycle,
 * exclusively from RentalReturnService::completeReturn(). This is the
 * counterpart to AgreementSigned (the start of the lifecycle): together they
 * are what CLAUDE.md's module scope calls RentalCreated/RentalEnded — a rental
 * IS a signed Agreement in this architecture, so no separate "Rental" entity
 * or duplicate pair of events exists; these two ARE that pair.
 */
class ReturnInspectionCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ReturnInspection $inspection,
    ) {}
}
