<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Services\BaseService;
use Carbon\Carbon;
use RuntimeException;

/**
 * Splits an agreement's current open invoice across a mid-cycle vehicle change
 * (CLAUDE.md "Prorated Vehicle Change").
 *
 * THE INVARIANT (CLAUDE.md, non-negotiable): the two prorated amounts MUST sum
 * EXACTLY to the original invoice total — no rounding gap. This is guaranteed by
 * splitting the ORIGINAL total by integer days, not by re-rating the new vehicle:
 *
 *     old_vehicle_amount = intdiv(total * old_days, total_days)
 *     new_vehicle_amount = total - old_vehicle_amount   ← remainder, zero gap
 *
 * The new vehicle's own daily rate takes over from the NEXT billing period via
 * the new agreement version; it does not affect this in-flight split. ($newVehicle
 * is accepted for the caller's contract but is intentionally not used to compute
 * the split — see the invariant above.)
 *
 * PURE CALCULATION — this performs only READS (it loads the open invoice). It
 * never writes, voids, or mutates anything, so it is safe to call for a preview
 * before the admin confirms the change.
 *
 * All arithmetic is in integer cents. No floats, ever.
 */
class ProrationService extends BaseService
{
    /**
     * @return array{
     *     old_vehicle_days: int,
     *     new_vehicle_days: int,
     *     old_vehicle_amount: int,
     *     new_vehicle_amount: int,
     *     total_days_in_period: int,
     * }
     */
    public function calculate(Agreement $agreement, Carbon $changeDate, Vehicle $newVehicle): array
    {
        $invoice = $this->currentOpenInvoice($agreement);

        if ($invoice === null) {
            throw new RuntimeException(
                "Agreement #{$agreement->id} has no open invoice to prorate."
            );
        }

        $periodStart = $invoice->billing_period_start->copy()->startOfDay();
        $periodEnd = $invoice->billing_period_end->copy()->startOfDay();
        $change = $changeDate->copy()->startOfDay();

        // Clamp the change date into the period so day counts can never go
        // negative or exceed the period (a change outside the period bills wholly
        // to one side).
        if ($change->lt($periodStart)) {
            $change = $periodStart->copy();
        } elseif ($change->gt($periodEnd)) {
            $change = $periodEnd->copy();
        }

        // Days used on the old vehicle = changeDate − periodStart.
        // Days remaining for the new vehicle = periodEnd − changeDate.
        // By construction old_days + new_days = total_days (exclusive diff), so
        // the split below is exhaustive with no leftover day.
        $totalDays = (int) $periodStart->diffInDays($periodEnd);
        $oldDays = (int) $periodStart->diffInDays($change);
        $newDays = $totalDays - $oldDays;

        $total = (int) $invoice->total;

        // Split the ORIGINAL total by days; the new amount absorbs the rounding
        // remainder so the two ALWAYS sum to exactly $total.
        $oldAmount = $totalDays > 0 ? intdiv($total * $oldDays, $totalDays) : 0;
        $newAmount = $total - $oldAmount;

        return [
            'old_vehicle_days' => $oldDays,
            'new_vehicle_days' => $newDays,
            'old_vehicle_amount' => $oldAmount,
            'new_vehicle_amount' => $newAmount,
            'total_days_in_period' => $totalDays,
        ];
    }

    /**
     * The agreement's current open (un-voided, unpaid) invoice — the one a
     * vehicle change splits. Open = not paid and not cancelled. Tenant-scoped
     * via HasTenant. Returns the most recent if somehow more than one exists.
     */
    public function currentOpenInvoice(Agreement $agreement): ?Invoice
    {
        return Invoice::query()
            ->where('agreement_id', $agreement->id)
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->latest('id')
            ->first();
    }
}
