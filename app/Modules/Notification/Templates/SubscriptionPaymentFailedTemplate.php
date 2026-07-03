<?php

namespace App\Modules\Notification\Templates;

use App\Modules\SaasCore\Models\Subscription;

/**
 * "Your subscription payment failed" — sent to the TENANT ADMIN when Stripe
 * reports a failed subscription payment (past_due). Pure: builds content from
 * the subscription (plan eager-loaded by the listener).
 */
class SubscriptionPaymentFailedTemplate
{
    use FormatsNotifications;

    public function build(Subscription $subscription): NotificationContent
    {
        $planName = $subscription->plan?->name ?? 'your plan';

        $subject = 'Action required — your DVARO subscription payment failed';

        $email = $this->emailHtml($subject, [
            'Hi,',
            "The latest payment for your \"{$planName}\" subscription could not be processed.",
            'Stripe will automatically retry the charge. To avoid any interruption to your account, please make sure your card details are up to date.',
            'If the payment continues to fail, your subscription may be cancelled.',
        ]);

        $sms = "Your DVARO subscription payment for \"{$planName}\" failed. Please update your card details to avoid interruption.";

        return new NotificationContent($subject, $email, $sms);
    }
}
