<?php

namespace App\Console\Commands;

use App\Modules\SaasCore\Services\StripeSyncService;
use Illuminate\Console\Command;

/**
 * Mirrors every active plan into Stripe (Product + monthly/annual Prices).
 * Idempotent — safe to run repeatedly; run once before going live and after
 * any plan or price change. Requires STRIPE_SECRET in the environment.
 */
class SyncStripePlansCommand extends Command
{
    protected $signature = 'stripe:sync-plans';

    protected $description = 'Create/update Stripe Products and Prices for all active plans (idempotent)';

    public function handle(StripeSyncService $sync): int
    {
        $result = $sync->syncAllPlans();

        $this->info("Stripe plan sync complete: {$result['synced']} synced, {$result['failed']} failed.");

        if ($result['failed'] > 0) {
            $this->error('Some plans failed to sync — see the application log.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
