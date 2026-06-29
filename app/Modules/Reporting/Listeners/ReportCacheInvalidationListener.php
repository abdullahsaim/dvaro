<?php

namespace App\Modules\Reporting\Listeners;

use App\Modules\Invoice\Events\PaymentReceived;
use App\Modules\Reporting\Services\ReportCacheService;

/**
 * Busts the revenue and overdue report caches for a tenant when a payment lands,
 * so those two reports reflect the new cash immediately instead of waiting for
 * the 30-/5-minute TTL.
 *
 * Synchronous (NOT queued) and cheap: PaymentReceived fires inside a bound-tenant
 * web request, and we target the exact tenant by the payment's tenant_id (no
 * reliance on the bound tenant). Other report caches age out on their own TTL.
 */
class ReportCacheInvalidationListener
{
    public function __construct(private readonly ReportCacheService $cache) {}

    public function handle(PaymentReceived $event): void
    {
        $tenantId = (int) $event->payment->tenant_id;

        $this->cache->invalidate(ReportCacheService::TYPE_REVENUE, $tenantId);
        $this->cache->invalidate(ReportCacheService::TYPE_OVERDUE, $tenantId);
    }
}
