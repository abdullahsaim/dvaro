<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\Customer\Models\Customer;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\InvoiceItem;
use App\Modules\Invoice\Models\Payment;
use App\Modules\Workshop\Models\ServiceLog;
use App\Services\BaseService;
use Carbon\Carbon;

/**
 * ReportingService — every analytics query for the tenant app.
 *
 * RULES (CLAUDE.md + this session):
 *   - All methods are tenant-scoped. They rely on the bound current_tenant +
 *     the HasTenant global scope; they are only ever called inside a request (or
 *     a job that has bound the tenant), never cross-tenant. No withoutGlobalScope
 *     anywhere here — isolation must hold.
 *   - All monetary values are returned as integer CENTS. Formatting to AUD
 *     happens at the edge (Vue useCurrency / export views).
 *   - All date ranges accept Carbon instances ([$from, $to]).
 *
 * Heavy aggregation is pushed into SQL (GROUP BY) to stay memory-frugal on the
 * 8GB VPS. Results are cached by ReportCacheService — this class never caches.
 */
class ReportingService extends BaseService
{
    private const TZ = 'Australia/Sydney';

    /**
     * Revenue per calendar month, by PAYMENT RECEIPT date (payments.paid_at).
     *
     * Revenue belongs to the period when cash was actually received, not when
     * the invoice was issued — so this sums Payment.amount, grouped by the month
     * of paid_at. Tenant-scoped via Payment's HasTenant.
     *
     * @return list<array{month: string, revenue: int}>  revenue in cents
     */
    public function revenueByPeriod(Carbon $from, Carbon $to): array
    {
        return Payment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw("to_char(paid_at, 'YYYY-MM') as ym, SUM(amount) as revenue")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::createFromFormat('Y-m', $row->ym)->format('M Y'),
                'revenue' => (int) $row->revenue,
            ])
            ->all();
    }

    /**
     * Revenue per vehicle from PAID invoices, with days rented in the window.
     *
     * Sums invoice_items.amount for items on paid invoices issued within the
     * range, grouped by vehicle. days_rented is the (de-duplicated) number of
     * rental days that vehicle was on an agreement inside the window.
     *
     * @return list<array{vehicle: string, revenue: int, days_rented: int}>
     */
    public function revenueByVehicle(Carbon $from, Carbon $to): array
    {
        $rows = InvoiceItem::query()
            ->whereNotNull('vehicle_id')
            ->whereHas('invoice', fn ($q) => $q
                ->where('status', Invoice::STATUS_PAID)
                ->whereBetween('created_at', [$from, $to]))
            ->selectRaw('vehicle_id, SUM(amount) as revenue')
            ->groupBy('vehicle_id')
            ->get();

        // Include soft-deleted vehicles: a vehicle may be archived yet still own
        // revenue history. withTrashed drops only the SoftDelete scope — the
        // tenant scope still applies.
        $vehicles = Vehicle::withTrashed()
            ->whereIn('id', $rows->pluck('vehicle_id'))
            ->get()
            ->keyBy('id');

        return $rows
            ->map(fn ($row) => [
                'vehicle' => $this->vehicleLabel($vehicles->get($row->vehicle_id), (int) $row->vehicle_id),
                'revenue' => (int) $row->revenue,
                'days_rented' => $this->rentedDaysForVehicle((int) $row->vehicle_id, $from, $to),
            ])
            ->sortByDesc('revenue')
            ->values()
            ->all();
    }

    /**
     * Utilisation per vehicle over the window: total days, days rented and the
     * resulting percentage. Excludes archived (soft-deleted) vehicles — they are
     * not part of the live fleet being utilised.
     *
     * @return list<array{vehicle: string, total_days: int, days_rented: int, utilisation: int}>
     */
    public function fleetUtilisation(Carbon $from, Carbon $to): array
    {
        $totalDays = $this->daysBetween($from, $to);

        return Vehicle::query()
            ->orderBy('registration_number')
            ->get()
            ->map(function (Vehicle $vehicle) use ($from, $to, $totalDays) {
                $rented = $this->rentedDaysForVehicle((int) $vehicle->id, $from, $to);

                return [
                    'vehicle' => $this->vehicleLabel($vehicle, (int) $vehicle->id),
                    'total_days' => $totalDays,
                    'days_rented' => $rented,
                    'utilisation' => $totalDays > 0 ? (int) round($rented / $totalDays * 100) : 0,
                ];
            })
            ->all();
    }

    /**
     * Every invoice that is overdue: explicitly flagged overdue, OR still owing
     * past its due date (and not paid/cancelled). Always live (5-min cache).
     *
     * @return list<array{invoice_id: int, customer: string, total: int, outstanding: int, days_overdue: int, due_date: ?string}>
     */
    public function overduePayments(): array
    {
        $today = Carbon::today(self::TZ);

        return Invoice::query()
            ->with('customer:id,name')
            ->where(function ($q) use ($today) {
                $q->where('status', Invoice::STATUS_OVERDUE)
                    ->orWhere(fn ($q2) => $q2
                        ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', $today));
            })
            ->get()
            ->map(fn (Invoice $invoice) => [
                'invoice_id' => (int) $invoice->id,
                'customer' => $invoice->customer?->name ?? '—',
                'total' => (int) $invoice->total,
                'outstanding' => (int) ($invoice->total - $invoice->paid_amount),
                'days_overdue' => $invoice->due_date !== null
                    ? (int) $invoice->due_date->startOfDay()->diffInDays($today, false)
                    : 0,
                'due_date' => $invoice->due_date?->toDateString(),
            ])
            // A row flagged overdue but since settled in full should not show.
            ->filter(fn ($row) => $row['outstanding'] > 0)
            ->sortByDesc('days_overdue')
            ->values()
            ->all();
    }

    /**
     * Workshop performance for jobs COMPLETED in the window: totals plus a
     * per-mechanic breakdown. Costs in cents; duration in hours.
     *
     * @return array{total_jobs: int, total_labour_cost: int, total_parts_cost: int, average_duration_hours: float, by_mechanic: list<array<string, mixed>>}
     */
    public function workshopPerformance(Carbon $from, Carbon $to): array
    {
        $logs = ServiceLog::query()
            ->where('status', ServiceLog::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->with(['mechanic:id,name', 'parts:id,service_log_id,total_cost'])
            ->get();

        $totalLabour = (int) $logs->sum('labour_cost');
        $totalParts = (int) $logs->sum(fn (ServiceLog $log) => $log->parts->sum('total_cost'));

        $durations = $logs
            ->filter(fn (ServiceLog $log) => $log->started_at !== null && $log->completed_at !== null)
            ->map(fn (ServiceLog $log) => $log->started_at->diffInMinutes($log->completed_at));
        $avgHours = $durations->isNotEmpty()
            ? round($durations->avg() / 60, 1)
            : 0.0;

        $byMechanic = $logs
            ->groupBy('mechanic_id')
            ->map(function ($group) {
                $labour = (int) $group->sum('labour_cost');
                $parts = (int) $group->sum(fn (ServiceLog $log) => $log->parts->sum('total_cost'));

                return [
                    'mechanic' => $group->first()->mechanic?->name ?? '—',
                    'jobs' => $group->count(),
                    'labour_cost' => $labour,
                    'parts_cost' => $parts,
                    'total_cost' => $labour + $parts,
                ];
            })
            ->sortByDesc('total_cost')
            ->values()
            ->all();

        return [
            'total_jobs' => $logs->count(),
            'total_labour_cost' => $totalLabour,
            'total_parts_cost' => $totalParts,
            'average_duration_hours' => $avgHours,
            'by_mechanic' => $byMechanic,
        ];
    }

    /**
     * New customers per calendar month within the window (by created_at).
     * Counts archived customers too — a signup still happened.
     *
     * @return list<array{month: string, customers: int}>
     */
    public function customerGrowth(Carbon $from, Carbon $to): array
    {
        return Customer::withTrashed()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("to_char(created_at, 'YYYY-MM') as ym, COUNT(*) as total")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($row) => [
                'month' => Carbon::createFromFormat('Y-m', $row->ym)->format('M Y'),
                'customers' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * Maintenance cost (labour + parts) per vehicle for jobs completed in the
     * window. Costs in cents.
     *
     * @return list<array{vehicle: string, jobs: int, labour_cost: int, parts_cost: int, total_cost: int}>
     */
    public function maintenanceCosts(Carbon $from, Carbon $to): array
    {
        return ServiceLog::query()
            ->where('status', ServiceLog::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->with(['vehicle' => fn ($q) => $q->withTrashed(), 'parts:id,service_log_id,total_cost'])
            ->get()
            ->groupBy('vehicle_id')
            ->map(function ($group) {
                $labour = (int) $group->sum('labour_cost');
                $parts = (int) $group->sum(fn (ServiceLog $log) => $log->parts->sum('total_cost'));
                $vehicle = $group->first()->vehicle;

                return [
                    'vehicle' => $this->vehicleLabel($vehicle, (int) $group->first()->vehicle_id),
                    'jobs' => $group->count(),
                    'labour_cost' => $labour,
                    'parts_cost' => $parts,
                    'total_cost' => $labour + $parts,
                ];
            })
            ->sortByDesc('total_cost')
            ->values()
            ->all();
    }

    /**
     * High-level KPIs for the reporting dashboard. Money in cents.
     *
     * @return array<string, mixed>
     */
    public function dashboardStats(): array
    {
        $fy = $this->australianFY();
        $monthStart = Carbon::now(self::TZ)->startOfMonth();
        $monthEnd = Carbon::now(self::TZ)->endOfMonth();

        $revenueThisMonth = (int) Payment::query()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('amount');

        $activeRentals = Agreement::query()
            ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
            ->count();

        $overdue = $this->overduePayments();

        $utilisation = $this->fleetUtilisation($fy['from'], $fy['to']);
        $totalDays = array_sum(array_column($utilisation, 'total_days'));
        $rentedDays = array_sum(array_column($utilisation, 'days_rented'));

        $vehiclesDueService = Vehicle::query()
            ->whereNotNull('next_service_due')
            ->whereDate('next_service_due', '<=', Carbon::now(self::TZ)->addDays(30))
            ->count();

        return [
            'revenue_this_month' => $revenueThisMonth,
            'active_rentals' => $activeRentals,
            'overdue_count' => count($overdue),
            'overdue_total' => array_sum(array_column($overdue, 'outstanding')),
            'fleet_utilisation' => $totalDays > 0 ? (int) round($rentedDays / $totalDays * 100) : 0,
            'vehicles_due_service' => $vehiclesDueService,
        ];
    }

    /**
     * Lightweight summary for the main tenant dashboard (Session 8 page).
     * Deliberately NOT cached — these are cheap indexed counts and an
     * outstanding balance that should always read fresh.
     *
     * @return array{outstanding_balance: int, active_rentals: int, vehicles_available: int}
     */
    public function tenantDashboardSummary(): array
    {
        $outstanding = (int) Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->selectRaw('COALESCE(SUM(total - paid_amount), 0) as owed')
            ->value('owed');

        return [
            'outstanding_balance' => $outstanding,
            'active_rentals' => Agreement::query()
                ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
                ->count(),
            'vehicles_available' => Vehicle::query()->available()->count(),
        ];
    }

    /**
     * The current Australian financial year (1 July – 30 June), evaluated in the
     * Australia/Sydney timezone. On/after 1 July the FY starts this calendar
     * year; before 1 July it started last calendar year.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function australianFY(): array
    {
        $now = Carbon::now(self::TZ);
        $startYear = $now->month >= 7 ? $now->year : $now->year - 1;

        return [
            'from' => Carbon::create($startYear, 7, 1, 0, 0, 0, self::TZ)->startOfDay(),
            'to' => Carbon::create($startYear + 1, 6, 30, 0, 0, 0, self::TZ)->endOfDay(),
        ];
    }

    /**
     * Distinct rental days a vehicle was under a signed/active agreement within
     * [$from, $to]. Overlapping agreement versions are merged so a day is never
     * counted twice. An open-ended agreement (no end_date) is clamped to $to.
     */
    private function rentedDaysForVehicle(int $vehicleId, Carbon $from, Carbon $to): int
    {
        $windowStart = $from->copy()->startOfDay();
        $windowEnd = $to->copy()->startOfDay();

        $intervals = Agreement::query()
            ->where('vehicle_id', $vehicleId)
            ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
            ->whereNotNull('start_date')
            ->get(['start_date', 'end_date'])
            ->map(function (Agreement $agreement) use ($windowStart, $windowEnd) {
                $start = $agreement->start_date->copy()->startOfDay()->max($windowStart);
                $end = ($agreement->end_date?->copy()->startOfDay() ?? $windowEnd)->min($windowEnd);

                return $end->gte($start) ? ['start' => $start, 'end' => $end] : null;
            })
            ->filter()
            ->sortBy(fn ($i) => $i['start']->timestamp)
            ->values();

        // Merge overlapping/adjacent intervals, then sum inclusive day counts.
        $days = 0;
        $curStart = null;
        $curEnd = null;

        foreach ($intervals as $interval) {
            if ($curStart === null) {
                $curStart = $interval['start'];
                $curEnd = $interval['end'];

                continue;
            }

            if ($interval['start']->lte($curEnd->copy()->addDay())) {
                // Overlapping or contiguous — extend the current run.
                $curEnd = $curEnd->max($interval['end']);
            } else {
                $days += $curStart->diffInDays($curEnd) + 1;
                $curStart = $interval['start'];
                $curEnd = $interval['end'];
            }
        }

        if ($curStart !== null) {
            $days += $curStart->diffInDays($curEnd) + 1;
        }

        return $days;
    }

    /** Inclusive whole-day count between two dates. */
    private function daysBetween(Carbon $from, Carbon $to): int
    {
        return (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
    }

    /** "ABC-123 Toyota Camry", falling back to an id when the vehicle is gone. */
    private function vehicleLabel(?Vehicle $vehicle, int $vehicleId): string
    {
        if ($vehicle === null) {
            return "Vehicle #{$vehicleId}";
        }

        return trim("{$vehicle->registration_number} {$vehicle->make} {$vehicle->model}");
    }
}
