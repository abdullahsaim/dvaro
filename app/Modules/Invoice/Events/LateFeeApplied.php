<?php

namespace App\Modules\Invoice\Events;

use App\Modules\Invoice\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a late fee is applied to an overdue invoice, exclusively from
 * ApplyLateFeeAction. Carries the invoice and the fee amount (integer cents).
 *
 * No listeners exist yet — customer notification ("a late fee has been applied")
 * attaches in the Notification session.
 */
class LateFeeApplied
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly int $amount,
    ) {}
}
