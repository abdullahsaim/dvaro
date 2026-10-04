<?php

namespace App\Console\Commands;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Finds (and optionally fixes) vehicles whose daily_rate was corrupted by a
 * bug in the Fleet create/edit forms: they posted the raw number the user
 * typed (e.g. "45" for $45/day) straight to a column that stores CENTS, with
 * no dollars→cents conversion. So "45" was stored as 45 cents — $0.45/day.
 *
 * Every vehicle created or edited through the UI before the forms were fixed
 * is affected. A genuine daily rate under $10 is implausible in this market,
 * so that is the review threshold — this NEVER auto-corrects without --fix,
 * and even with --fix it only multiplies by 100 (reversing the exact bug),
 * never guesses at an intended value.
 *
 * Safe to run repeatedly: a vehicle already at or above the threshold is left
 * alone, so running --fix twice cannot double-correct a row.
 */
class AuditDailyRatesCommand extends Command
{
    protected $signature = 'vehicles:audit-daily-rates
        {--tenant= : Limit to one tenant slug}
        {--threshold=1000 : Flag any daily_rate below this many CENTS (default $10.00)}
        {--fix : Multiply flagged rows by 100 (reverses the bug) instead of only listing them}';

    protected $description = 'List (or fix) vehicles whose daily rate was stored as dollars-typed-as-cents.';

    public function handle(): int
    {
        $threshold = (int) $this->option('threshold');
        $fix = (bool) $this->option('fix');
        $slug = $this->option('tenant');

        if ($threshold <= 0) {
            $this->error('--threshold must be a positive number of cents.');

            return self::FAILURE;
        }

        $tenants = Tenant::query()
            ->when($slug, fn ($q) => $q->where('slug', $slug))
            ->get();

        if ($tenants->isEmpty()) {
            $this->error($slug ? "No tenant with slug [{$slug}]." : 'No tenants found.');

            return self::FAILURE;
        }

        $flagged = 0;
        $fixed = 0;

        foreach ($tenants as $tenant) {
            app()->instance('current_tenant', $tenant);

            $vehicles = Vehicle::query()
                ->where('daily_rate', '<', $threshold)
                ->orderBy('registration_number')
                ->get(['id', 'registration_number', 'daily_rate']);

            foreach ($vehicles as $vehicle) {
                $flagged++;
                $was = number_format($vehicle->daily_rate / 100, 2);

                if ($fix) {
                    $corrected = $vehicle->daily_rate * 100;
                    $vehicle->forceFill(['daily_rate' => $corrected])->save();
                    $fixed++;
                    $this->line("  {$tenant->slug}: {$vehicle->registration_number}  \${$was} → \$".number_format($corrected / 100, 2));
                } else {
                    $this->line("  {$tenant->slug}: {$vehicle->registration_number}  currently \${$was}/day");
                }
            }

            app()->forgetInstance('current_tenant');
        }

        $this->newLine();

        if ($flagged === 0) {
            $this->info('No vehicles below the threshold — nothing to review.');

            return self::SUCCESS;
        }

        $this->info($fix
            ? "{$fixed} vehicle(s) corrected (×100)."
            : "{$flagged} vehicle(s) below \$".number_format($threshold / 100, 2).'/day. Review the list above, then re-run with --fix once you are satisfied every row is a genuine case of this bug — some may be legitimately cheap (e.g. an old run-around car) and should be left alone.');

        return self::SUCCESS;
    }
}
