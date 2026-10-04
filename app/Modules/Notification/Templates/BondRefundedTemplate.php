<?php

namespace App\Modules\Notification\Templates;

use App\Modules\Rental\Models\ReturnInspection;

/**
 * "Your bond has been settled" — sent to the customer once a vehicle return
 * is recorded and the bond refund figure is final. Sent even when the refund
 * is $0 (bond fully withheld) — the customer is still owed an explanation.
 * Pure: builds content from the inspection, no I/O.
 */
class BondRefundedTemplate
{
    use FormatsNotifications;

    public function build(ReturnInspection $inspection): NotificationContent
    {
        $customerName = $inspection->customer?->name ?? 'there';
        $bond = $this->money($inspection->bond_amount);
        $deduction = $this->money($inspection->deduction_amount);
        $refund = $this->money($inspection->refund_amount);

        $subject = 'Your bond has been settled';

        $lines = [
            "Hi {$customerName},",
            "Your vehicle return has been recorded and your bond of {$bond} has been settled.",
        ];

        if ($inspection->deduction_amount > 0) {
            $reason = $inspection->deduction_reason ?? 'vehicle condition at return';
            $lines[] = "A deduction of {$deduction} was applied ({$reason}).";
        }

        $lines[] = $inspection->refund_amount > 0
            ? "Your refund of {$refund} will be processed shortly."
            : 'No amount remains to be refunded.';

        $email = $this->emailHtml($subject, $lines);

        $sms = $inspection->refund_amount > 0
            ? "Hi {$customerName}, your bond has been settled. Refund of {$refund} will be processed shortly."
            : "Hi {$customerName}, your bond has been settled. No amount remains to be refunded.";

        return new NotificationContent($subject, $email, $sms);
    }
}
