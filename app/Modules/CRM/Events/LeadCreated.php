<?php

namespace App\Modules\CRM\Events;

use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant staff member creates a lead (CreateLeadAction), before
 * the customer has filled anything in.
 *
 * No listeners yet — a future Notification-session listener will send the
 * intake link via SMS/email/WhatsApp. For now the admin copies the link by hand.
 */
class LeadCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}
}
