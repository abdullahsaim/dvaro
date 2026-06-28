<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;

/**
 * "Payment received — thank you" — sent to the customer after a payment is
 * recorded against an invoice. Pure: builds content from invoice + payment.
 */
class PaymentReceivedTemplate
{
    use FormatsNotifications;

    public function build(Invoice $invoice, Payment $payment): NotificationContent
    {
        $customerName = $invoice->customer?->name ?? 'there';
        $amount = $this->money((int) $payment->amount);
        $outstanding = $this->money((int) $invoice->outstandingAmount());

        $subject = 'Payment received — thank you';

        $email = $this->emailHtml($subject, [
            "Hi {$customerName},",
            "We've received your payment of {$amount}. Thank you.",
            $invoice->isPaid()
                ? 'This invoice is now paid in full.'
                : "Remaining balance on this invoice: {$outstanding}.",
        ]);

        $sms = $invoice->isPaid()
            ? "Hi {$customerName}, we received your payment of {$amount}. This invoice is paid in full. Thank you."
            : "Hi {$customerName}, we received your payment of {$amount}. Remaining balance: {$outstanding}.";

        return new NotificationContent($subject, $email, $sms);
    }
}
