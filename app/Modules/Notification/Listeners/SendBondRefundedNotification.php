<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Notification\Templates\BondRefundedTemplate;
use App\Modules\Rental\Events\BondRefunded;

/**
 * Notifies the customer once a vehicle return is recorded and the bond is
 * settled (refund, partial deduction, or fully withheld). Queued.
 */
class SendBondRefundedNotification extends QueuedNotificationListener
{
    public function handle(BondRefunded $event): void
    {
        $inspection = $event->inspection;

        $tenant = $this->bindTenant((int) $inspection->tenant_id);
        if ($tenant === null) {
            return;
        }

        try {
            $inspection->loadMissing('customer');
            $customer = $inspection->customer;

            if ($customer === null) {
                return;
            }

            $content = (new BondRefundedTemplate)->build($inspection);
            $this->notifyCustomer($tenant, $customer, $content, 'bond.refunded');
        } finally {
            $this->forgetTenant();
        }
    }
}
