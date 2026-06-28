<?php

namespace App\Providers;

use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\CRM\Events\LeadSubmitted;
use App\Modules\Invoice\Events\InvoiceGenerated;
use App\Modules\Invoice\Events\LateFeeApplied;
use App\Modules\Invoice\Events\PaymentReceived;
use App\Modules\Invoice\Listeners\GenerateFirstInvoice;
use App\Modules\Notification\Listeners\SendAgreementSignedNotification;
use App\Modules\Notification\Listeners\SendInvoiceGeneratedNotification;
use App\Modules\Notification\Listeners\SendLateFeeNotification;
use App\Modules\Notification\Listeners\SendLeadSubmittedNotification;
use App\Modules\Notification\Listeners\SendPaymentReceivedNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

/**
 * Maps domain events to their listeners.
 *
 * Notification listeners are queued (QueuedNotificationListener). The only
 * synchronous listener is GenerateFirstInvoice — the first invoice must exist
 * by redirect. It is ordered FIRST on AgreementSigned so the invoice (and its
 * own InvoiceGenerated → customer notification) is raised before the
 * "agreement signed" notice goes out.
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        AgreementSigned::class => [
            GenerateFirstInvoice::class,            // synchronous — billing
            SendAgreementSignedNotification::class, // queued — customer notice
        ],
        InvoiceGenerated::class => [
            SendInvoiceGeneratedNotification::class,
        ],
        PaymentReceived::class => [
            SendPaymentReceivedNotification::class,
        ],
        LateFeeApplied::class => [
            SendLateFeeNotification::class,
        ],
        LeadSubmitted::class => [
            SendLeadSubmittedNotification::class,
        ],
    ];

    /**
     * Auto-discovery is off — the map above is the single source of truth.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
