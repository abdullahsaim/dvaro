<?php

namespace App\Modules\Notification\Templates;

use App\Modules\SaasCore\Models\Subscription;

/**
 * "Your subscription has been cancelled" — sent to the TENANT ADMIN when the
 * gateway subscription is definitively cancelled (period lapsed after a
 * cancel request, or collection failed for good). Pure.
 */
class SubscriptionCancelledTemplate
{
    use FormatsNotifications;

    public function build(Subscription $subscription): NotificationContent
    {
        $planName = $subscription->plan?->name ?? 'your plan';

        $subject = 'Your DVARO subscription has been cancelled';

        $email = $this->emailHtml($subject, [
            'Hi,',
            "Your \"{$planName}\" subscription has been cancelled and will no longer renew.",
            'You can resubscribe at any time from the Billing page in your DVARO workspace.',
            'If this cancellation is unexpected, please contact support.',
        ]);

        $sms = "Your DVARO subscription (\"{$planName}\") has been cancelled. You can resubscribe from the Billing page.";

        return new NotificationContent($subject, $email, $sms);
    }
}
