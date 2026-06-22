<?php

namespace App\Modules\Invoice\Services;

use App\Exceptions\VehicleChangeNotAllowedException;
use App\Modules\Agreement\DTOs\CreateAgreementDTO;
use App\Modules\Agreement\Models\Agreement;
use App\Modules\Agreement\Services\AgreementService;
use App\Modules\Finance\Models\LedgerEntry;
use App\Modules\Finance\Services\LedgerService;
use App\Modules\Fleet\Actions\ChangeVehicleStatusAction;
use App\Modules\Fleet\Events\VehicleChanged;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\DTOs\CreateInvoiceDTO;
use App\Modules\Invoice\Models\Invoice;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Performs a mid-cycle vehicle change on an active agreement (CLAUDE.md
 * "Prorated Vehicle Change"). The whole operation is atomic — every table is
 * updated inside ONE DB::transaction; any failure rolls the lot back.
 *
 * Steps (all atomic):
 *   1. Prorate the current open invoice's total across old/new vehicle by days
 *      (ProrationService — split sums EXACTLY to the original total).
 *   2. Void the open invoice (status=cancelled) AND post a compensating ledger
 *      credit of −original_total. The ledger is append-only, so the original
 *      charge cannot be deleted — it must be reversed. CreateInvoiceService then
 *      re-posts each prorated charge, so the net balance is unchanged but
 *      correctly re-attributed:  +total  −total  +old  +new  =  +total.
 *   3. Raise two prorated invoices (old days / new days) — each auto-appends its
 *      own ledger charge via CreateInvoiceService (we do NOT double-append).
 *   4. Create a new agreement version carrying the new vehicle (immutable
 *      history; the new draft must be re-signed).
 *   5. Old vehicle → available, new vehicle → rented (ChangeVehicleStatusAction,
 *      the only sanctioned status path; fires VehicleStatusChanged).
 *   6. Fire VehicleChanged.
 *
 * All amounts are integer cents.
 */
class VehicleChangeService extends BaseService
{
    /** Agreements that can have their vehicle changed (mirrors recurring billing). */
    private const CHANGEABLE_STATUSES = [
        Agreement::STATUS_SIGNED,
        Agreement::STATUS_ACTIVE,
    ];

    public function __construct(
        private readonly ProrationService $proration,
        private readonly CreateInvoiceService $invoices,
        private readonly AgreementService $agreements,
        private readonly LedgerService $ledger,
        private readonly ChangeVehicleStatusAction $changeStatus,
    ) {}

    /**
     * @return array{
     *     cancelled_invoice: Invoice,
     *     old_invoice: Invoice,
     *     new_invoice: Invoice,
     *     new_version: Agreement,
     *     proration: array<string, int>,
     * }
     */
    public function execute(Agreement $agreement, Vehicle $newVehicle, Carbon $changeDate): array
    {
        $this->guard($agreement, $newVehicle);

        return DB::transaction(function () use ($agreement, $newVehicle, $changeDate): array {
            $oldVehicle = $agreement->vehicle;

            // (1) Pure calculation against the current open invoice.
            $split = $this->proration->calculate($agreement, $changeDate, $newVehicle);
            $openInvoice = $this->proration->currentOpenInvoice($agreement);

            if ($openInvoice === null) {
                // calculate() would already have thrown; this satisfies the type
                // checker and guards a race where the invoice was paid/voided.
                throw new VehicleChangeNotAllowedException(
                    'There is no open invoice to prorate for this agreement.'
                );
            }

            $periodStart = $openInvoice->billing_period_start->copy();
            $periodEnd = $openInvoice->billing_period_end->copy();
            $change = $changeDate->copy()->startOfDay();
            $originalTotal = (int) $openInvoice->total;

            // (2) Void the open invoice and reverse its ledger charge.
            $openInvoice->update(['status' => Invoice::STATUS_CANCELLED]);

            $this->ledger->append(
                tenantId: $openInvoice->tenant_id,
                customerId: $openInvoice->customer_id,
                type: LedgerEntry::TYPE_DISCOUNT,
                amount: -$originalTotal,
                description: "Reversal of cancelled Invoice #{$openInvoice->id} (vehicle change)",
                referenceType: 'invoice',
                referenceId: $openInvoice->id,
            );

            // (3) Two prorated invoices. CreateInvoiceService appends each charge.
            $oldInvoice = $this->invoices->execute(new CreateInvoiceDTO(
                customer_id: (int) $agreement->customer_id,
                agreement_id: (int) $agreement->id,
                items: [[
                    'description' => "Prorated rental — outgoing vehicle ({$split['old_vehicle_days']} days)",
                    'amount' => $split['old_vehicle_amount'],
                    'vehicle_id' => $oldVehicle?->id,
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $change->copy()->subDay()->toDateString(),
                ]],
                type: Invoice::TYPE_PRORATED,
                billing_period_start: $periodStart->toDateString(),
                billing_period_end: $change->copy()->subDay()->toDateString(),
                due_date: $periodStart->toDateString(),
            ));

            $newInvoice = $this->invoices->execute(new CreateInvoiceDTO(
                customer_id: (int) $agreement->customer_id,
                agreement_id: (int) $agreement->id,
                items: [[
                    'description' => "Prorated rental — incoming vehicle ({$split['new_vehicle_days']} days)",
                    'amount' => $split['new_vehicle_amount'],
                    'vehicle_id' => $newVehicle->id,
                    'period_start' => $change->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                ]],
                type: Invoice::TYPE_PRORATED,
                billing_period_start: $change->toDateString(),
                billing_period_end: $periodEnd->toDateString(),
                due_date: $change->toDateString(),
            ));

            // (4) New immutable version carrying the new vehicle. Same terms,
            // only vehicle_id changes; starts as a draft requiring re-sign.
            $newVersion = $this->agreements->createNewVersion(
                $agreement,
                $this->dtoForNewVehicle($agreement, $newVehicle),
            );

            // (5) Vehicle status transitions via the sanctioned action.
            if ($oldVehicle !== null) {
                $this->changeStatus->execute($oldVehicle, Vehicle::STATUS_AVAILABLE);
            }
            $this->changeStatus->execute($newVehicle, Vehicle::STATUS_RENTED);

            // (6) Domain event.
            if ($oldVehicle !== null) {
                VehicleChanged::dispatch($agreement, $oldVehicle, $newVehicle, $change);
            }

            return [
                'cancelled_invoice' => $openInvoice,
                'old_invoice' => $oldInvoice,
                'new_invoice' => $newInvoice,
                'new_version' => $newVersion,
                'proration' => $split,
            ];
        });
    }

    /**
     * Pre-transaction validation: agreement must be billable, the new vehicle
     * must be available and genuinely different from the current one.
     */
    private function guard(Agreement $agreement, Vehicle $newVehicle): void
    {
        if (! in_array($agreement->status, self::CHANGEABLE_STATUSES, true)) {
            throw new VehicleChangeNotAllowedException(
                'Only a signed or active agreement can have its vehicle changed.'
            );
        }

        if (! $newVehicle->isAvailable()) {
            throw new VehicleChangeNotAllowedException(
                'The selected vehicle is not available.'
            );
        }

        if ((int) $newVehicle->id === (int) $agreement->vehicle_id) {
            throw new VehicleChangeNotAllowedException(
                'The agreement is already on this vehicle.'
            );
        }
    }

    /**
     * Build the new-version DTO: the existing agreement's terms with the vehicle
     * swapped. (CreateAgreementDTO is readonly, so we construct rather than clone.)
     */
    private function dtoForNewVehicle(Agreement $agreement, Vehicle $newVehicle): CreateAgreementDTO
    {
        return new CreateAgreementDTO(
            customer_id: (int) $agreement->customer_id,
            vehicle_id: (int) $newVehicle->id,
            type: $agreement->type,
            billing_cycle: $agreement->billing_cycle,
            rate: (int) $agreement->rate,
            start_date: $agreement->start_date->format('Y-m-d'),
            billing_cycle_day: $agreement->billing_cycle_day,
            bond_amount: (int) $agreement->bond_amount,
            end_date: $agreement->end_date?->format('Y-m-d'),
            notes: $agreement->notes,
        );
    }
}
