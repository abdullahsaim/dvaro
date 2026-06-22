<?php

namespace App\Modules\CRM\Events;

use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an admin manually expires a lead's intake link (ExpireLeadAction).
 *
 * No listeners yet.
 */
class LeadExpired
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}
}
