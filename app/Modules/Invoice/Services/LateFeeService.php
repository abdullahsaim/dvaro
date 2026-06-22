<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Invoice\Actions\ApplyLateFeeAction;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\SaasCore\Models\Tenant;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Applies late fees to overdue invoices across every tenant.
 *
 * Runs with NO bound tenant (scheduled-command context), so it binds each tenant
 * before any tenant-scoped query — a bare Invoice::query() would otherwise throw
 * TenantNotResolvedException.
 *
 * Grace period is per-tenant (settings.late_fee_grace_days, default 3): a fee is
 * applied only once the invoice is more than that many days past its due date.
 *
 * IDEMPOTENT: an invoice that already carries a "Late fee" line item is skipped,
 * so the daily run never stacks repeated fees on the same invoice.
 *
 * NEVER throws — each failure (tenant- or invoice-level) is logged and the batch
 * continues.
 */
class LateFeeService extends BaseService
{
    private const DEFAULT_GRACE_DAYS = 3;

    /** Open statuses that can still accrue a late fee. */
    private const CHARGEABLE_STATUSES = [
        Invoice::STATUS_DRAFT,
        Invoice::STATUS_SENT,
        Invoice::STATUS_OVERDUE,
    ];

    public function __construct(
        private readonly ApplyLateFeeAction $applyLateFee,
    ) {}

    public function applyDueLateFees(): void
    {
        $today = Carbon::today();

        foreach (Tenant::all() as $tenant) {
            app()->instance('current_tenant', $tenant);

            try {
                $graceDays = (int) (($tenant->settings['late_fee_grace_days'] ?? self::DEFAULT_GRACE_DAYS));
                $cutoff = $today->copy()->subDays(max($graceDays, 0));

                $invoices = Invoice::query()
                    ->whereIn('status', self::CHARGEABLE_STATUSES)
                    // Still owing money (total > paid_amount).
                    ->whereColumn('paid_amount', '<', 'total')
                    // Past the grace period.
                    ->whereDate('due_date', '<=', $cutoff)
                    // Idempotency: no existing late-fee line item.
                    ->whereDoesntHave('items', function ($query): void {
                        $query->where('description', 'like', ApplyLateFeeAction::LATE_FEE_DESCRIPTION.'%');
                    })
                    ->get();

                foreach ($invoices as $invoice) {
                    try {
                        $this->applyLateFee->execute($invoice);
                    } catch (Throwable $e) {
                        Log::error('LateFeeService: invoice fee failed', [
                            'tenant_id' => $tenant->id,
                            'invoice_id' => $invoice->id,
                            'message' => $e->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                Log::error('LateFeeService: tenant batch failed', [
                    'tenant_id' => $tenant->id,
                    'message' => $e->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('current_tenant');
            }
        }
    }
}
