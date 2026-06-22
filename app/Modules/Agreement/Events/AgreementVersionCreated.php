<?php

namespace App\Modules\Agreement\Events;

use App\Modules\Agreement\Models\Agreement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new version of an agreement is created, exclusively from
 * AgreementService::createNewVersion(). Carries BOTH the previous version and
 * the newly created one — the old agreement is never mutated.
 *
 * No listeners exist yet — they live in later sessions (re-sign notification,
 * audit log).
 */
class AgreementVersionCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Agreement $oldAgreement,
        public readonly Agreement $newAgreement,
    ) {}
}
