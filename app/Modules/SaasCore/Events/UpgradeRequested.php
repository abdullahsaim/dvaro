<?php

namespace App\Modules\SaasCore\Events;

use App\Modules\SaasCore\Models\UpgradeRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a tenant admin submits a plan-upgrade request
 * (UpgradeRequestController@store).
 *
 * No listeners yet — the super-admin notification on new requests is a later
 * (Notification) session. The request is already persisted when this fires.
 */
class UpgradeRequested
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly UpgradeRequest $upgradeRequest,
    ) {}
}
