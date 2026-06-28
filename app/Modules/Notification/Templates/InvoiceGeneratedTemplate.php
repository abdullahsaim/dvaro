<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Invoice\Models\Invoice;

/**
 * "A new invoice is available" — sent to the customer when an invoice is
 * generated (recurring or prorated). Pure: builds content from the invoice.
 */
class InvoiceGeneratedTemplate
{
    use FormatsNotifications;

    public function build(Invoice $invoice): NotificationContent
    {
        $customerName = $invoice->customer?->name ?? 'there';
        $total = $this->money((int) $invoice->total);
        $due = $this->date($invoice->due_date);

        $subject = 'A new invoice is available';

        $email = $this->emailHtml($subject, [
            "Hi {$customerName},",
            "A new invoice for {$total} has been generated.",
            "Due date: {$due}.",
            'You can view and pay this invoice from your customer portal.',
        ]);

        $sms = "Hi {$customerName}, a new invoice for {$total} is available (due {$due}). View and pay it in your portal.";

        return new NotificationContent($subject, $email, $sms);
    }
}
