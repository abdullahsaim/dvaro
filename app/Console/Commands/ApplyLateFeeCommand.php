<?php

namespace App\Console\Commands;

use App\Modules\Invoice\Services\LateFeeService;
use Illuminate\Console\Command;

/**
 * Applies late fees to overdue invoices past their grace period across every
 * tenant.
 *
 * Scheduled daily (routes/console.php). Delegates to
 * LateFeeService::applyDueLateFees(), which is tenant-aware, idempotent, and
 * never throws.
 */
class ApplyLateFeeCommand extends Command
{
    protected $signature = 'invoices:apply-late-fees';

    protected $description = 'Apply late fees to overdue invoices past their grace period (all tenants).';

    public function handle(LateFeeService $service): int
    {
        $this->info('Applying late fees to overdue invoices…');

        $service->applyDueLateFees();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
