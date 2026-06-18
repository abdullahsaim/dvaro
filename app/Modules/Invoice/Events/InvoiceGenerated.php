<?php

namespace App\Modules\Invoice\Events;

use App\Modules\Invoice\Models\Invoice;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an invoice has been generated (CLAUDE.md: "EVENTS — EVERY
 * IMPORTANT ACTION"). Carries the new Invoice.
 *
 * No listeners exist yet — notification (email/SMS/WhatsApp) dispatch is wired
 * in the later Notification session. Keeping the event here means that wiring is
 * additive and the creation path never changes.
 */
class InvoiceGenerated
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Invoice $invoice,
    ) {}
}
