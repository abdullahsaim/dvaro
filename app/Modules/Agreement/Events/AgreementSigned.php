<?php

namespace App\Modules\Agreement\Events;

use App\Modules\Agreement\Models\Agreement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an agreement is signed (status draft → signed), exclusively from
 * AgreementService::sign(). Carries the signed agreement.
 *
 * No listeners exist yet — PDF generation is dispatched directly from sign()
 * via GenerateAgreementPdfJob, not through a listener. Future listeners (invoice
 * triggering, notifications) attach here in later sessions.
 */
class AgreementSigned
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Agreement $agreement,
    ) {}
}
