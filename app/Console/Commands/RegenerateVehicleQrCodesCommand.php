<?php

namespace App\Console\Commands;

use App\Modules\Fleet\Models\Vehicle;
use App\Modules\SaasCore\Models\Tenant;
use App\Modules\Workshop\Actions\GenerateVehicleQrAction;
use App\Scopes\TenantScope;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Regenerates every vehicle's QR code so its stored image encodes a SIGNED scan
 * URL (GenerateVehicleQrAction). Vehicles created before QR signing landed have
 * a stored SVG pointing at the old unsigned URL — scanning those now shows the
 * "expired" page. Running this once re-signs them all.
 *
 * Runs with NO bound tenant (CLI context), so it binds each tenant before any
 * tenant-scoped query — a bare Vehicle::query() would otherwise throw
 * TenantNotResolvedException. The HMAC token is deterministic, so regenerating
 * never changes a vehicle's token; only the stored signed URL is refreshed.
 * Per-vehicle failures are logged and skipped so one bad row can't abort the run.
 */
class RegenerateVehicleQrCodesCommand extends Command
{
    protected $signature = 'vehicles:regenerate-qr-codes';

    protected $description = 'Regenerate signed QR codes for all vehicles (all tenants).';

    public function handle(GenerateVehicleQrAction $action): int
    {
        $this->info('Regenerating signed QR codes…');

        $total = 0;
        $failed = 0;

        foreach (Tenant::all() as $tenant) {
            app()->instance('current_tenant', $tenant);

            try {
                // withTrashed not needed — archived vehicles never get scanned;
                // TenantScope is satisfied by the bound tenant above.
                Vehicle::query()->withoutGlobalScope(TenantScope::class)
                    ->where('tenant_id', $tenant->id)
                    ->each(function (Vehicle $vehicle) use ($action, &$total, &$failed): void {
                        try {
                            $action->execute($vehicle);
                            $total++;
                        } catch (Throwable $e) {
                            $failed++;
                            Log::error('RegenerateVehicleQrCodes: vehicle failed', [
                                'tenant_id' => $vehicle->tenant_id,
                                'vehicle_id' => $vehicle->id,
                                'message' => $e->getMessage(),
                            ]);
                        }
                    });
            } catch (Throwable $e) {
                Log::error('RegenerateVehicleQrCodes: tenant sweep failed', [
                    'tenant_id' => $tenant->id,
                    'message' => $e->getMessage(),
                ]);
            } finally {
                app()->forgetInstance('current_tenant');
            }
        }

        $this->info("Done. Regenerated {$total} vehicle QR code(s), {$failed} failed.");

        return self::SUCCESS;
    }
}
