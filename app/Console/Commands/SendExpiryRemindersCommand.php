<?php

namespace App\Console\Commands;

use App\Modules\Notification\Services\ExpiryReminderService;
use Illuminate\Console\Command;

/**
 * Sends 14-day expiry reminders (vehicle registration/insurance/service +
 * agreement end) to each tenant's admin.
 *
 * Scheduled daily (routes/console.php). Delegates to ExpiryReminderService,
 * which is tenant-aware, idempotent, and never throws — so a single tenant
 * failure can never abort the run.
 */
class SendExpiryRemindersCommand extends Command
{
    protected $signature = 'notifications:send-expiry-reminders';

    protected $description = 'Send 14-day expiry reminders to tenant admins (all tenants).';

    public function handle(ExpiryReminderService $service): int
    {
        $this->info('Sending expiry reminders…');

        $service->sweep();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
