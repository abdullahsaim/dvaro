<?php

namespace App\Console\Commands;

use App\Modules\SaasCore\Services\PaypalSyncService;
use Illuminate\Console\Command;

/**
 * Mirrors every active plan into PayPal (catalog Product + monthly/annual
 * billing Plans). Idempotent — safe to run repeatedly; run once before going
 * live and after any plan or price change. Requires PAYPAL_CLIENT_ID/SECRET.
 */
class SyncPaypalPlansCommand extends Command
{
    protected $signature = 'paypal:sync-plans';

    protected $description = 'Create/update PayPal catalog Products and billing Plans for all active plans (idempotent)';

    public function handle(PaypalSyncService $sync): int
    {
        $result = $sync->syncAllPlans();

        $this->info("PayPal plan sync complete: {$result['synced']} synced, {$result['failed']} failed.");

        if ($result['failed'] > 0) {
            $this->error('Some plans failed to sync — see the application log.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
