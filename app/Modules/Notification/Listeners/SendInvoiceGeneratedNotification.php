<?php

namespace App\Modules\Notification\Listeners;

use App\Modules\Invoice\Events\InvoiceGenerated;
use App\Modules\Notification\Templates\InvoiceGeneratedTemplate;

/**
 * Notifies the customer that a new invoice is available. Queued.
 */
class SendInvoiceGeneratedNotification extends QueuedNotificationListener
{
    public function handle(InvoiceGenerated $event): void
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

            $content = (new InvoiceGeneratedTemplate())->build($invoice);
            $this->notifyCustomer($tenant, $customer, $content, 'invoice.generated');
        } finally {
            $this->forgetTenant();
        }
    }
}
