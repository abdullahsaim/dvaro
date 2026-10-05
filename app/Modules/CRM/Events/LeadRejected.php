<?php

namespace App\Modules\CRM\Events;

use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an admin rejects a lead (RejectLeadAction) — a deliberate "not a
 * fit" decision, distinct from LeadExpired (the intake link simply died).
 *
 * No listeners yet.
 */
class LeadRejected
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}
}
