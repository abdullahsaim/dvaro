<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Invoice\Models\Invoice;

/**
 * "A late fee has been applied" — sent to the customer when ApplyLateFeeAction
 * adds a late fee to an overdue invoice. Pure: builds content from the invoice
 * and the fee amount (integer cents).
 */
class LateFeeAppliedTemplate
{
    use FormatsNotifications;

    public function build(Invoice $invoice, int $amount): NotificationContent
    {
        $customerName = $invoice->customer?->name ?? 'there';
        $fee = $this->money($amount);
        $outstanding = $this->money((int) $invoice->outstandingAmount());

        $subject = 'A late fee has been applied to your invoice';

        $email = $this->emailHtml($subject, [
            "Hi {$customerName},",
            "A late fee of {$fee} has been applied to your overdue invoice.",
            "Total outstanding balance: {$outstanding}.",
            'Please make payment as soon as possible to avoid further fees.',
        ]);

        $sms = "Hi {$customerName}, a late fee of {$fee} was applied to your overdue invoice. Outstanding balance: {$outstanding}. Please pay as soon as possible.";

        return new NotificationContent($subject, $email, $sms);
    }
}
