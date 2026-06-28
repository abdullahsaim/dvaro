<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Invoice\Events\PaymentReceived;
use App\Modules\Notification\Templates\PaymentReceivedTemplate;

/**
 * Notifies the customer that their payment was received. Queued.
 */
class SendPaymentReceivedNotification extends QueuedNotificationListener
{
    public function handle(PaymentReceived $event): void
    {
        $invoice = $event->invoice;

        $tenant = $this->bindTenant((int) $invoice->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            $invoice->loadMissing('customer');
            $customer = $invoice->customer;

            if ($customer === null) {
                return;
            }

            $content = (new PaymentReceivedTemplate())->build($invoice, $event->payment);
            $this->notifyCustomer($tenant, $customer, $content, 'payment.received');
        } finally {
            $this->forgetTenant();
        }
    }
}
