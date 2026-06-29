<?php

namespace App\Modules\Reporting\Services;

use App\Services\BaseService;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cache-aside wrapper over ReportingService.
 *
 * Reports are expensive aggregations on a memory-constrained VPS, so the
 * controller reads through this service — never ReportingService directly — and
 * the DB is only hit on a cache miss (CLAUDE.md: "never hit DB on every page
 * load").
 *
 * Key pattern:  report:{tenant_id}:{type}:{md5(params)}
 * TTL:          30 minutes (most reports), 5 minutes (overdue — must stay live).
 *
 * Invalidation: cache tags are unavailable on the default stores (array in
 * tests, and database is a supported store), so we keep a per-(tenant,type)
 * INDEX of live keys and forget them on invalidate(). Store-agnostic.
 */
class ReportCacheService extends BaseService
{
    private const TTL_DEFAULT = 1800; // 30 minutes
    private const TTL_OVERDUE = 300;  // 5 minutes
    private const TTL_INDEX = 86400;  // key-index lives a day (cheap bookkeeping)

    public const TYPE_REVENUE = 'revenue';
    public const TYPE_REVENUE_BY_VEHICLE = 'revenue_by_vehicle';
    public const TYPE_FLEET = 'fleet';
    public const TYPE_OVERDUE = 'overdue';
    public const TYPE_WORKSHOP = 'workshop';
    public const TYPE_CUSTOMERS = 'customers';
    public const TYPE_MAINTENANCE = 'maintenance';
    public const TYPE_DASHBOARD = 'dashboard';

    public function __construct(private readonly ReportingService $reporting) {}

    public function revenueByPeriod(Carbon $from, Carbon $to): array
    {
        return $this->remember(self::TYPE_REVENUE, $this->range($from, $to), self::TTL_DEFAULT,
            fn () => $this->reporting->revenueByPeriod($from, $to));
    }

    public function revenueByVehicle(Carbon $from, Carbon $to): array
    {
        return $this->remember(self::TYPE_REVENUE_BY_VEHICLE, $this->range($from, $to), self::TTL_DEFAULT,
            fn () => $this->reporting->revenueByVehicle($from, $to));
    }

    public function fleetUtilisation(Carbon $from, Carbon $to): array
    {
        return $this->remember(self::TYPE_FLEET, $this->range($from, $to), self::TTL_DEFAULT,
            fn () => $this->reporting->fleetUtilisation($from, $to));
    }

    public function overduePayments(): array
    {
        return $this->remember(self::TYPE_OVERDUE, [], self::TTL_OVERDUE,
            fn () => $this->reporting->overduePayments());
    }

    public function workshopPerformance(Carbon $from, Carbon $to): array
    {
        return $this->remember(self::TYPE_WORKSHOP, $this->range($from, $to), self::TTL_DEFAULT,
            fn () => $this->reporting->workshopPerformance($from, $to));
    }

    public function customerGrowth(Carbon $from, Carbon $to): array
    {
        return $this->remember(self::TYPE_CUSTOMERS, $this->range($from, $to), self::TTL_DEFAULT,
            fn () => $this->reporting->customerGrowth($from, $to));
    }

    public function maintenanceCosts(Carbon $from, Carbon $to): array
    {
        return $this->remember(self::TYPE_MAINTENANCE, $this->range($from, $to), self::TTL_DEFAULT,
            fn () => $this->reporting->maintenanceCosts($from, $to));
    }

    public function dashboardStats(): array
    {
        return $this->remember(self::TYPE_DASHBOARD, [], self::TTL_DEFAULT,
            fn () => $this->reporting->dashboardStats());
    }

    /**
     * Clear every cached report of $reportType for a tenant (defaults to the
     * bound current_tenant). Called by ReportCacheInvalidationListener when a
     * payment lands, and available for manual busting.
     */
    public function invalidate(string $reportType, ?int $tenantId = null): void
    {
        $tenantId ??= $this->tenantId();
        $indexKey = $this->indexKey($reportType, $tenantId);

        foreach (Cache::get($indexKey, []) as $cacheKey) {
            Cache::forget($cacheKey);
        }

        Cache::forget($indexKey);
    }

    /**
     * Cache-aside read with a hit/miss + timing log (used by the verify step to
     * confirm the second hit is served from cache).
     */
    private function remember(string $type, array $params, int $ttl, Closure $callback): array
    {
        $key = $this->key($type, $params);
        $this->trackKey($type, $key);

        $hit = Cache::has($key);
        $start = microtime(true);
        $result = Cache::remember($key, $ttl, $callback);

        Log::debug('report.cache', [
            'type' => $type,
            'key' => $key,
            'hit' => $hit,
            'ms' => round((microtime(true) - $start) * 1000, 2),
        ]);

        return $result;
    }

    private function key(string $type, array $params): string
    {
        return "report:{$this->tenantId()}:{$type}:".md5(json_encode($params));
    }

    private function indexKey(string $type, ?int $tenantId = null): string
    {
        $tenantId ??= $this->tenantId();

        return "report_index:{$tenantId}:{$type}";
    }

    /** Record a live cache key in its type index so invalidate() can find it. */
    private function trackKey(string $type, string $key): void
    {
        $indexKey = $this->indexKey($type);
        $keys = Cache::get($indexKey, []);

        if (! in_array($key, $keys, true)) {
            $keys[] = $key;
            Cache::put($indexKey, $keys, self::TTL_INDEX);
        }
    }

    /** @return array{from: string, to: string} */
    private function range(Carbon $from, Carbon $to): array
    {
        return ['from' => $from->toDateString(), 'to' => $to->toDateString()];
    }

    private function tenantId(): int
    {
        return (int) app('current_tenant')->id;
    }
}
