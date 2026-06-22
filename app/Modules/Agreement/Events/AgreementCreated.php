<?php

namespace App\Modules\Agreement\Events;

use App\Modules\Agreement\Models\Agreement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a new agreement is created (status=draft, version=1), exclusively
 * from AgreementService::create(). Carries the new agreement.
 *
 * No listeners exist yet — they live in later sessions (audit log, etc.).
 */
class AgreementCreated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Agreement $agreement,
    ) {}
}
