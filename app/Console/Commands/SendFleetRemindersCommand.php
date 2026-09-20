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
    protected $signature = 'notifications:send-fleet-reminders {--hour= : Only sweep tenants whose LOCAL time is this hour (0-23); omit to sweep every tenant now}';

    protected $description = 'Send the daily fleet reminder digest (rego, insurance, service by date/km) to all tenant staff.';

    public function handle(FleetReminderService $service): int
    {
        // Scheduled HOURLY with --hour=7: each tenant is swept when its own
        // timezone reads 7am, so everyone gets the digest at the same LOCAL
        // time. Run without --hour to send immediately (support/manual use).
        $hour = $this->option('hour');

        $this->info($hour === null ? 'Sending fleet reminders…' : "Sending fleet reminders for tenants at {$hour}:00 local…");

        $service->sweep($hour === null ? null : (int) $hour);

        $this->info('Done.');

        return self::SUCCESS;
    }
}
