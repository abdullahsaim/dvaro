<?php

namespace App\Modules\CMS\Events;

use App\Modules\CMS\Models\DemoRequest;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a prospect submits the public demo-request (or contact) form.
 *
 * No listeners yet — a future Notification-session listener will alert the
 * super admin. Listener-less, so (per the convention here) it is NOT registered
 * in EventServiceProvider; it is dispatched directly from DemoRequestController.
 */
class DemoRequestSubmitted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly DemoRequest $demoRequest,
    ) {}
}
