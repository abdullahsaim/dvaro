<?php

namespace App\Console\Commands;

use App\Modules\Notification\Services\FleetReminderService;
use Illuminate\Console\Command;

/**
 * Sends the daily fleet reminder digest (registration, insurance, service by
 * date or km) to ALL staff of each tenant.
 *
 * Scheduled daily at 07:00 Australia/Sydney (routes/console.php) so the digest
 * lands at the start of the working day. Delegates to FleetReminderService,
 * which is tenant-aware, idempotent (fleet_reminders ledger) and never throws.
 */
class SendFleetRemindersCommand extends Command
{
    protected $signature = 'notifications:send-fleet-reminders';

    protected $description = 'Send the daily fleet reminder digest (rego, insurance, service by date/km) to all tenant staff.';

    public function handle(FleetReminderService $service): int
    {
        $this->info('Sending fleet reminders…');

        $service->sweep();

        $this->info('Done.');

        return self::SUCCESS;
    }
}
