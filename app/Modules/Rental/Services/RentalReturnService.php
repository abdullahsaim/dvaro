<?php

namespace App\Modules\Rental\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Actions\ChangeVehicleStatusAction;
use App\Modules\Fleet\Actions\RecordOdometerReadingAction;
use App\Modules\Fleet\Models\OdometerReading;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Rental\DTOs\CompleteReturnDTO;
use App\Modules\Rental\Events\BondRefunded;
use App\Modules\Rental\Events\ReturnInspectionCompleted;
use App\Modules\Rental\Models\ReturnInspection;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Closes out a rental: records the vehicle's return condition, settles the
 * bond (deduction + refund), completes the agreement, and returns the
 * vehicle to service. This IS the "RentalEnded" moment (see
 * ReturnInspectionCompleted's docblock).
 */
class RentalReturnService extends BaseService
{
    public function __construct(
        private readonly LedgerService $ledger,
        private readonly ChangeVehicleStatusAction $changeVehicleStatus,
        private readonly RecordOdometerReadingAction $recordOdometer,
    ) {}

    public function completeReturn(Agreement $agreement, CompleteReturnDTO $dto): ReturnInspection
    {
        if ($dto->deduction_amount > $agreement->bond_amount) {
            throw ValidationException::withMessages([
                'deduction_amount' => __('common.rental.deduction_exceeds_bond'),
            ]);
        }

        return DB::transaction(function () use ($agreement, $dto): ReturnInspection {
            $vehicle = $agreement->vehicle;

            $this->recordOdometer->execute(
                $vehicle,
                $dto->odometer_reading,
                OdometerReading::SOURCE_RETURN_INSPECTION,
                OdometerReading::ACTOR_TENANT_USER,
                $dto->inspected_by,
            );

            $refundAmount = $agreement->bond_amount - $dto->deduction_amount;

            $inspection = ReturnInspection::create([
                'agreement_id' => $agreement->id,
                'vehicle_id' => $vehicle->id,
                'customer_id' => $agreement->customer_id,
                'odometer_reading' => $dto->odometer_reading,
                'fuel_level' => $dto->fuel_level,
                'condition_notes' => $dto->condition_notes,
                'damage_found' => $dto->damage_found,
                'damage_description' => $dto->damage_description,
                'needs_workshop' => $dto->needs_workshop,
                'deduction_amount' => $dto->deduction_amount,
                'deduction_reason' => $dto->deduction_reason,
                'bond_amount' => $agreement->bond_amount,
                'refund_amount' => $refundAmount,
                'inspected_by' => $dto->inspected_by,
                'completed_at' => now(),
            ]);

            if ($agreement->bond_amount > 0) {
                if ($dto->deduction_amount > 0) {
                    $this->ledger->append(
                        tenantId: $agreement->tenant_id,
                        customerId: $agreement->customer_id,
                        type: LedgerEntry::TYPE_BOND_DEDUCTION,
                        amount: -$dto->deduction_amount,
                        description: "Bond deduction for agreement #{$agreement->id}: {$dto->deduction_reason}",
                        referenceType: 'agreement',
                        referenceId: $agreement->id,
                    );
                }

                if ($refundAmount > 0) {
                    $this->ledger->append(
                        tenantId: $agreement->tenant_id,
                        customerId: $agreement->customer_id,
                        type: LedgerEntry::TYPE_BOND_REFUND,
                        amount: -$refundAmount,
                        description: "Bond refunded for agreement #{$agreement->id}",
                        referenceType: 'agreement',
                        referenceId: $agreement->id,
                    );
                }
            }

            $agreement->update(['status' => Agreement::STATUS_COMPLETED]);

            $this->changeVehicleStatus->execute(
                $vehicle,
                $dto->needs_workshop ? Vehicle::STATUS_MAINTENANCE : Vehicle::STATUS_AVAILABLE,
            );

            ReturnInspectionCompleted::dispatch($inspection);
            BondRefunded::dispatch($inspection);

            return $inspection;
        });
    }
}
