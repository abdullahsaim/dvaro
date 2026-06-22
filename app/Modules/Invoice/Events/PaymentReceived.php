<?php

namespace App\Modules\Invoice\Events;

use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a payment is recorded against an invoice, exclusively from
 * RecordPaymentAction. Carries the (updated) invoice and the payment.
 *
 * No listeners exist yet — customer "payment received" receipt/notification
 * attaches in the Notification session.
 */
class PaymentReceived
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
        public readonly Payment $payment,
    ) {}
}
