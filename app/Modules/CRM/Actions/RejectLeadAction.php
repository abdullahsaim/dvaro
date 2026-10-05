<?php

namespace App\Modules\CRM\Actions;

use App\Actions\BaseAction;
use App\Exceptions\LeadNotRejectableException;
use App\Modules\CRM\Events\LeadRejected;
use App\Modules\CRM\Models\Lead;

/**
 * Marks a lead "not a fit" — a deliberate decision, distinct from
 * ExpireLeadAction (which just kills a stale intake link). A converted lead
 * already has a real customer behind it and can never be rejected.
 *
 * Fires LeadRejected. Idempotent — re-rejecting an already-rejected lead is a
 * harmless no-op write.
 */
class RejectLeadAction extends BaseAction
{
    public function execute(Lead $lead): Lead
    {
        if ($lead->status === Lead::STATUS_CONVERTED) {
            throw new LeadNotRejectableException;
        }

        $lead->update(['status' => Lead::STATUS_REJECTED]);

        LeadRejected::dispatch($lead);

        return $lead;
    }
}
