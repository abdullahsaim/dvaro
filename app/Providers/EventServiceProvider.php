<?php

namespace App\Providers;

use App\Modules\Agreement\Events\AgreementSigned;
use App\Modules\CRM\Events\LeadSubmitted;
use App\Modules\Finance\Events\ExpenseRecorded;
use App\Modules\Finance\Events\ExpenseUpdated;
use App\Modules\Finance\Events\ExpenseVoided;
use App\Modules\Finance\Listeners\ExpenseReportCacheListener;
use App\Modules\Invoice\Events\InvoiceGenerated;
use App\Modules\Invoice\Events\LateFeeApplied;
use App\Modules\Invoice\Events\PaymentReceived;
use App\Modules\Invoice\Listeners\GenerateFirstInvoice;
use App\Modules\Notification\Listeners\SendAgreementSignedNotification;
use App\Modules\Notification\Listeners\SendBondRefundedNotification;
use App\Modules\Notification\Listeners\SendInvoiceGeneratedNotification;
use App\Modules\Notification\Listeners\SendLateFeeNotification;
use App\Modules\Notification\Listeners\SendLeadSubmittedNotification;
use App\Modules\Notification\Listeners\SendMaintenanceCompletedNotification;
use App\Modules\Notification\Listeners\SendMaintenanceStartedNotification;
use App\Modules\Notification\Listeners\SendPaymentReceivedNotification;
use App\Modules\Notification\Listeners\SendSubscriptionCancelledNotification;
use App\Modules\Notification\Listeners\SendSubscriptionPaymentFailedNotification;
use App\Modules\Notification\Listeners\SendTenantApprovedNotification;
use App\Modules\Notification\Listeners\SendTenantEmailVerification;
use App\Modules\Notification\Listeners\SendTenantRejectedNotification;
use App\Modules\Rental\Events\BondRefunded;
use App\Modules\Reporting\Listeners\ReportCacheInvalidationListener;
use App\Modules\SaasCore\Events\SubscriptionCancelled;
use App\Modules\SaasCore\Events\SubscriptionPaymentFailed;
use App\Modules\SaasCore\Events\TenantRegistered;
use App\Modules\SuperAdmin\Events\TenantApproved;
use App\Modules\SuperAdmin\Events\TenantRejected;
use App\Modules\Workshop\Events\MaintenanceCompleted;
use App\Modules\Workshop\Events\MaintenanceStarted;
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
            // Sync — busts the revenue + overdue report caches for the tenant.
            ReportCacheInvalidationListener::class,
        ],
        LateFeeApplied::class => [
            SendLateFeeNotification::class,
        ],
        LeadSubmitted::class => [
            SendLeadSubmittedNotification::class,
        ],
        BondRefunded::class => [
            SendBondRefundedNotification::class,
        ],
        TenantRegistered::class => [
            SendTenantEmailVerification::class,
        ],
        TenantApproved::class => [
            SendTenantApprovedNotification::class,
        ],
        TenantRejected::class => [
            SendTenantRejectedNotification::class,
        ],
        MaintenanceStarted::class => [
            SendMaintenanceStartedNotification::class,
        ],
        MaintenanceCompleted::class => [
            SendMaintenanceCompletedNotification::class,
        ],
        // Stripe webhook outcomes — tenant-admin ops notices (queued, email-only).
        SubscriptionPaymentFailed::class => [
            SendSubscriptionPaymentFailedNotification::class,
        ],
        SubscriptionCancelled::class => [
            SendSubscriptionCancelledNotification::class,
        ],
        // Expenses (Session 32) — sync; bust the expenses + profit-per-vehicle caches.
        ExpenseRecorded::class => [
            ExpenseReportCacheListener::class,
        ],
        ExpenseUpdated::class => [
            ExpenseReportCacheListener::class,
        ],
        ExpenseVoided::class => [
            ExpenseReportCacheListener::class,
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
