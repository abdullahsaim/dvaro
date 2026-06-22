<?php

namespace App\Modules\CRM\Actions;

use App\Actions\BaseAction;
use App\Modules\CRM\Events\LeadExpired;
use App\Modules\CRM\Models\Lead;

/**
 * Manually expires a lead's intake link. After this the public intake form
 * aborts 410 and Lead::isExpired() returns true regardless of token_expires_at.
 *
 * Fires LeadExpired. Idempotent — re-expiring an already-expired lead is a
 * harmless no-op write.
 */
class ExpireLeadAction extends BaseAction
{
    public function execute(Lead $lead): Lead
    {
        $lead->update(['expires_manually' => true]);

        LeadExpired::dispatch($lead);

        return $lead;
    }
}
