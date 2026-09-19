<?php

namespace App\Modules\Fleet\Actions;

use App\Actions\BaseAction;
use App\Modules\Fleet\Events\OdometerReadingRecorded;
use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Records an odometer reading — the ONE sanctioned path for every source
 * (staff entry, mechanic service log, and — once the Rental module lands — the
 * return inspection). Appends an OdometerReading row, moves the vehicle's
 * current_odometer forward and fires OdometerReadingRecorded.
 *
 * Odometers never go backwards: a reading LOWER than the current one is
 * rejected (ValidationException on `reading`). The vehicle row is locked for
 * the duration so two concurrent readings can't interleave. An equal reading
 * is accepted (vehicle hasn't moved) and still logged.
 */
class RecordOdometerReadingAction extends BaseAction
{
    /**
     * @param  string|null  $actorType  OdometerReading::ACTOR_* or null (system)
     *
     * @throws ValidationException when $reading is below the current odometer
     */
    public function execute(
        Vehicle $vehicle,
        int $reading,
        string $source,
        ?string $actorType = null,
        ?int $actorId = null,
        ?int $serviceLogId = null,
    ): OdometerReading {
        if (! in_array($source, OdometerReading::SOURCES, true)) {
            throw new InvalidArgumentException("Invalid odometer source [{$source}].");
        }

        if ($reading < 0) {
            throw ValidationException::withMessages(['reading' => __('common.fleet.odometer_negative')]);
        }

        $record = DB::transaction(function () use ($vehicle, $reading, $source, $actorType, $actorId, $serviceLogId) {
            $locked = Vehicle::query()->lockForUpdate()->findOrFail($vehicle->id);

            if ($locked->current_odometer !== null && $reading < $locked->current_odometer) {
                throw ValidationException::withMessages([
                    'reading' => __('common.fleet.odometer_backwards', [
                        'current' => number_format($locked->current_odometer),
                    ]),
                ]);
            }

            $record = OdometerReading::create([
                'vehicle_id' => $locked->id,
                'reading' => $reading,
                'source' => $source,
                'recorded_by_type' => $actorType,
                'recorded_by_id' => $actorId,
                'service_log_id' => $serviceLogId,
                'recorded_at' => now(),
            ]);

            $locked->forceFill([
                'current_odometer' => $reading,
                'odometer_updated_at' => now(),
            ])->save();

            // Keep the caller's instance in sync.
            $vehicle->setRawAttributes($locked->getAttributes(), true);

            return $record;
        });

        OdometerReadingRecorded::dispatch($vehicle, $record);

        return $record;
    }
}
