<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Invoice\Events\LateFeeApplied;
use App\Modules\Notification\Templates\LateFeeAppliedTemplate;

/**
 * Notifies the customer that a late fee was applied to their overdue invoice.
 * Queued.
 */
class SendLateFeeNotification extends QueuedNotificationListener
{
    public function handle(LateFeeApplied $event): void
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

            $content = (new LateFeeAppliedTemplate())->build($invoice, $event->amount);
            $this->notifyCustomer($tenant, $customer, $content, 'invoice.late_fee');
        } finally {
            $this->forgetTenant();
        }
    }
}
