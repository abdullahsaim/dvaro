<?php

namespace App\Modules\CRM\Events;

use App\Modules\CRM\Models\Lead;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the prospective customer submits the PUBLIC intake form
 * (IntakeFormController@submit). Carries the freshly populated lead.
 *
 * No listeners yet — a future listener will notify the tenant that a lead has
 * come in (CLAUDE.md "Lead submitted" notification trigger).
 */
class LeadSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Lead $lead,
    ) {}
}
