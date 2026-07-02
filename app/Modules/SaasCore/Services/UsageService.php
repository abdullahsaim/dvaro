<?php

namespace App\Modules\SaasCore\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Reporting\Models\ReportExport;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\SaasCore\Models\TenantUser;
use App\Services\BaseService;

/**
 * Computes a tenant's current usage against its active plan's hard limits, for
 * the billing portal's usage meters.
 *
 * Queries run tenant-scoped: the billing portal is entered with the tenant
 * bound, and each counted model uses TenantScope, so counts are automatically
 * constrained to the current tenant. The counts mirror exactly what the
 * enforcement path meters (Vehicle::count / Customer::count / TenantUser::count),
 * so the bars never disagree with a PlanLimitExceededException.
 *
 * The metered keys (vehicles / staff / customers) map to real, enforced numeric
 * plan limits (max_vehicles / max_staff_users / max_customers). Storage is NOT
 * metered as a percentage — we store no byte totals — so it is reported as an
 * informational FILE COUNT only (stored PDFs + exports), never a progress bar.
 */
class UsageService extends BaseService
{
    /**
     * Metered display key => the plan limit key it is enforced against.
     */
    private const LIMIT_KEYS = [
        'vehicles' => 'max_vehicles',
        'staff' => 'max_staff_users',
        'customers' => 'max_customers',
    ];

    /**
     * Full usage snapshot for the billing page.
     *
     * @return array{
     *     vehicles: array{current:int,limit:int,percentage:int,approaching:bool},
     *     staff: array{current:int,limit:int,percentage:int,approaching:bool},
     *     customers: array{current:int,limit:int,percentage:int,approaching:bool},
     *     storage: array{files:int}
     * }
     */
    public function getUsage(Tenant $tenant): array
    {
        $usage = [];

        foreach (array_keys(self::LIMIT_KEYS) as $key) {
            $current = $this->currentCount($key);
            $limit = $this->limitFor($tenant, $key);

            $usage[$key] = [
                'current' => $current,
                'limit' => $limit,
                'percentage' => $this->percentage($current, $limit),
                'approaching' => $this->approaching($current, $limit),
            ];
        }

        // Storage: informational file count only (no byte totals are tracked, so
        // no percentage against max_storage_gb). Stored agreement + invoice PDFs
        // plus ready report exports.
        $usage['storage'] = [
            'files' => $this->storedFileCount(),
        ];

        return $usage;
    }

    /**
     * Usage percentage (0–100) for a metered display key. Unlimited => 0.
     */
    public function getUsagePercentage(string $key, Tenant $tenant): int
    {
        if (! array_key_exists($key, self::LIMIT_KEYS)) {
            return 0;
        }

        return $this->percentage($this->currentCount($key), $this->limitFor($tenant, $key));
    }

    /**
     * Whether usage exceeds 80% of a finite limit for a metered display key.
     */
    public function isApproachingLimit(string $key, Tenant $tenant): bool
    {
        if (! array_key_exists($key, self::LIMIT_KEYS)) {
            return false;
        }

        return $this->approaching($this->currentCount($key), $this->limitFor($tenant, $key));
    }

    /**
     * Live tenant-scoped count for a metered display key.
     */
    private function currentCount(string $key): int
    {
        return match ($key) {
            'vehicles' => Vehicle::count(),
            'staff' => TenantUser::count(),
            'customers' => Customer::count(),
            default => 0,
        };
    }

    /**
     * The active plan's limit for a metered key. -1 (unlimited) when the tenant
     * has no active plan or the plan does not define the limit.
     */
    private function limitFor(Tenant $tenant, string $key): int
    {
        $plan = $tenant->activePlan();

        if ($plan === null) {
            return -1;
        }

        return $plan->getLimit(self::LIMIT_KEYS[$key]);
    }

    private function percentage(int $current, int $limit): int
    {
        if ($limit <= 0) {
            return 0; // unlimited (or unset) — no meaningful percentage
        }

        return (int) min(100, (int) round($current / $limit * 100));
    }

    private function approaching(int $current, int $limit): bool
    {
        return $limit > 0 && ($current / $limit) > 0.8;
    }

    /**
     * Count of stored files across the tenant (informational). Tenant-scoped.
     */
    private function storedFileCount(): int
    {
        return Agreement::whereNotNull('pdf_path')->count()
            + Invoice::whereNotNull('pdf_path')->count()
            + ReportExport::whereNotNull('file_path')->count();
    }
}
