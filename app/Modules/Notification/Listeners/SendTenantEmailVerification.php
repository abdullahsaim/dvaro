<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Services\NotificationMatrix;
use App\Modules\Notification\Services\NotificationService;
use App\Modules\Notification\Services\TenantEmailVerificationNotifier;
use App\Modules\SaasCore\Events\TenantRegistered;
use App\Modules\SaasCore\Models\TenantUser;

/**
 * Sends the brand-new admin their email-verification link right after
 * onboarding. Queued like every other outbound email (CLAUDE.md).
 *
 * Soft verification: this does NOT gate access — the admin is already
 * auto-logged-in and using the full app by the time this lands (see
 * TenantRegistrationController). It only starts the trail; the dashboard
 * banner + resend action carry the rest.
 */
class SendTenantEmailVerification extends QueuedNotificationListener
{
    public function __construct(
        NotificationService $notifications,
        NotificationMatrix $matrix,
        private readonly TenantEmailVerificationNotifier $verification,
    ) {
        parent::__construct($notifications, $matrix);
    }

    public function handle(TenantRegistered $event): void
    {
        $tenant = $this->bindTenant((int) $event->tenant->id);
        if ($tenant === null) {
            return;
        }

        try {
            // Onboarding creates exactly one admin — the person who signed up.
            $admin = TenantUser::where('role', TenantUser::ROLE_ADMIN)->first();
            if ($admin === null) {
                return;
            }

            $this->verification->send($admin);
        } finally {
            $this->forgetTenant();
        }
    }
}
