<?php

namespace App\Console\Commands;

use App\Modules\Invoice\Services\RecurringInvoiceService;
use Illuminate\Console\Command;

/**
 * Raises the day's due recurring invoices across every tenant.
 *
 * Scheduled daily (routes/console.php). Delegates entirely to
 * RecurringInvoiceService::generateDue(), which is tenant-aware and never throws
 * — so a single tenant or agreement failure can never abort the run.
 */
class GenerateRecurringInvoicesCommand extends Command
{
    protected $signature = 'invoices:generate-recurring';

    protected $description = 'Generate recurring invoices for agreements due today (all tenants).';

    public function handle(RecurringInvoiceService $service): int
    {
        $this->info('Generating due recurring invoices…');

        $service->generateDue();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
