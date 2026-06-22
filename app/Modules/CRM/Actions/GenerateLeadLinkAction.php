<?php

namespace App\Modules\CRM\Actions;

use App\Actions\BaseAction;
use App\Modules\CRM\Models\Lead;
use Illuminate\Support\Facades\URL;

/**
 * Builds the public, signed intake-form URL for a lead.
 *
 * Uses a PERMANENT signed route (not temporarySignedRoute): link lifetime is a
 * lead-level concept (token_expires_at / expires_manually, checked via
 * Lead::isExpired()), NOT a property of the URL signature. The signature only
 * guarantees the {tenant_slug}/{token} pair wasn't tampered with — any edit
 * invalidates it and the public controller aborts 403.
 */
class GenerateLeadLinkAction extends BaseAction
{
    public function execute(Lead $lead): string
    {
        return URL::signedRoute('crm.intake.show', [
            // Resolve the slug from the lead's own tenant relation rather than
            // app('current_tenant'), so this is correct even if ever called with
            // no tenant bound.
            'tenant_slug' => $lead->tenant->slug,
            'token' => $lead->token,
        ]);
    }
}
