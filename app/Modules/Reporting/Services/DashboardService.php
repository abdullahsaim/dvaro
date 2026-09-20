<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Modules\CRM\Models\Lead;
use App\Modules\Finance\Services\ExpenseReportService;
use App\Modules\Fleet\Models\Vehicle;
use App\Modules\Invoice\Models\Invoice;
use App\Modules\Invoice\Models\Payment;
use App\Modules\SaasCore\Models\TenantUser;
use App\Modules\Workshop\Models\ServiceLog;
use App\Services\BaseService;
use Illuminate\Support\Carbon;

/**
 * What a rental company needs to SEE when it opens DVARO in the morning.
 *
 * The dashboard used to be six numbers. This assembles the three operational
 * blocks that replace them: what needs attention today, what the fleet is
 * doing, and what is happening this week.
 *
 * Design rules:
 *  - Every figure is a grouped/aggregate query. No N+1, no loading collections
 *    to count them — this runs on a shared 8GB VPS and must stay cheap.
 *  - Each "needs attention" row carries enough to act on: what, how urgent, and
 *    the id to link to. The dashboard is a to-do list, not a wall of numbers.
 *  - Money NEVER appears in the operational payload. The controller decides by
 *    role whether to ask for it at all, so a staff member's browser is not sent
 *    revenue it must then hide.
 */
class DashboardService extends BaseService
{
    /** Agreements ending within this many days count as "ending soon". */
    public const ENDING_SOON_DAYS = 14;

    /** Most rows of any one attention type (the rest become a "+N more"). */
    public const ATTENTION_LIMIT = 5;

    /** Months of history behind the revenue sparkline. */
    public const TREND_MONTHS = 6;

    public const SEVERITY_URGENT = 'urgent';

    public const SEVERITY_SOON = 'soon';

    /**
     * Everything needing a human today, most urgent first.
     *
     * $includeMoney gates the overdue-invoice group, which is the only part
     * that states amounts.
     *
     * @return array<string, mixed>
     */
    public function needsAttention(bool $includeMoney = true): array
    {
        $groups = [];

        if ($includeMoney) {
            $groups[] = $this->overdueInvoices();
        }

        $groups[] = $this->fleetExpiries();
        $groups[] = $this->agreementsEnding();
        $groups[] = $this->unconvertedLeads();
        $groups[] = $this->vehiclesOffRoad();
        $groups[] = $this->openWorkshopJobs();

        $groups = array_values(array_filter($groups, fn (array $g) => $g['count'] > 0));

        return [
            'groups' => $groups,
            'total' => array_sum(array_column($groups, 'count')),
            'urgent' => array_sum(array_map(
                fn (array $g) => $g['severity'] === self::SEVERITY_URGENT ? $g['count'] : 0,
                $groups,
            )),
        ];
    }

    /**
     * The fleet by status, as proportions of the whole — the six statuses from
     * CLAUDE.md, always all six, so an empty status still reads as zero rather
     * than vanishing.
     *
     * @return array<string, mixed>
     */
    public function fleetSnapshot(): array
    {
        $counts = Vehicle::query()
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $total = (int) $counts->sum();

        $statuses = array_map(fn (string $status) => [
            'status' => $status,
            'count' => (int) ($counts[$status] ?? 0),
            'percentage' => $total > 0 ? round(((int) ($counts[$status] ?? 0)) / $total * 100, 1) : 0.0,
        ], Vehicle::STATUSES);

        // Utilisation here is a LIVE snapshot (how much of the fleet is out
        // right now), deliberately not the period-average utilisation on the
        // reports page — the two answer different questions.
        $onRoad = (int) ($counts[Vehicle::STATUS_RENTED] ?? 0);

        return [
            'statuses' => $statuses,
            'total' => $total,
            'utilisation' => $total > 0 ? round($onRoad / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * The week ahead: what starts, what ends, what is booked in.
     *
     * @return array<string, mixed>
     */
    public function thisWeek(): array
    {
        $today = Carbon::today();
        $weekEnd = $today->copy()->addDays(7);

        $active = [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE];

        return [
            'starting' => Agreement::query()
                ->whereIn('status', $active)
                ->whereBetween('start_date', [$today, $weekEnd])
                ->count(),
            'ending' => Agreement::query()
                ->whereIn('status', $active)
                ->whereNotNull('end_date')
                ->whereBetween('end_date', [$today, $weekEnd])
                ->count(),
            'starting_today' => Agreement::query()
                ->whereIn('status', $active)
                ->whereDate('start_date', $today)
                ->count(),
            'ending_today' => Agreement::query()
                ->whereIn('status', $active)
                ->whereDate('end_date', $today)
                ->count(),
            'services_booked' => ServiceLog::query()
                ->whereIn('status', [ServiceLog::STATUS_PENDING, ServiceLog::STATUS_IN_PROGRESS])
                ->count(),
            'new_leads' => Lead::query()
                ->whereIn('status', [Lead::STATUS_NEW, Lead::STATUS_CONTACTED])
                ->whereNotNull('submitted_at')
                ->count(),
        ];
    }

    /**
     * The money block — admin and accounts only, and the controller decides
     * that before this is ever called.
     *
     * Revenue is CASH RECEIVED (payments), not invoiced value: "what came in
     * this month" is the question an owner is actually asking. Receivables are
     * the other side of it — invoiced and still owed — aged so that a $200 bill
     * sent yesterday is not lumped in with $5,000 ninety days late.
     *
     * @return array<string, mixed>
     */
    public function money(): array
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();
        $lastStart = $monthStart->copy()->subMonth();
        $lastEnd = $lastStart->copy()->endOfMonth();

        $thisMonth = $this->paymentsBetween($monthStart, $monthEnd);
        $lastMonth = $this->paymentsBetween($lastStart, $lastEnd);
        $expenses = (int) (app(ExpenseReportService::class)
            ->summary($monthStart, $monthEnd)['totals']['total'] ?? 0);

        return [
            'revenue_this_month' => $thisMonth,
            'revenue_last_month' => $lastMonth,
            // Percentage change, or null when last month was zero — dividing by
            // it would produce a meaningless "+∞%" on a company's first month.
            'revenue_change_pct' => $lastMonth > 0
                ? round((($thisMonth - $lastMonth) / $lastMonth) * 100, 1)
                : null,
            'expenses_this_month' => $expenses,
            'net_this_month' => $thisMonth - $expenses,
            'receivables' => $this->receivablesAged(),
            'trend' => $this->revenueTrend(),
        ];
    }

    /**
     * Everything invoiced and still owed, by age. "current" is not yet due —
     * money that is coming, not money that is late.
     *
     * @return array<string, mixed>
     */
    private function receivablesAged(): array
    {
        $today = Carbon::today();

        $rows = Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->selectRaw(
                'SUM(CASE WHEN due_date IS NULL OR due_date >= ? THEN total - paid_amount ELSE 0 END) as current,
                 SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total - paid_amount ELSE 0 END) as late_30,
                 SUM(CASE WHEN due_date < ? THEN total - paid_amount ELSE 0 END) as late_over_30',
                [
                    $today->toDateString(),
                    $today->toDateString(), $today->copy()->subDays(30)->toDateString(),
                    $today->copy()->subDays(30)->toDateString(),
                ],
            )
            ->first();

        $current = (int) ($rows->current ?? 0);
        $late30 = (int) ($rows->late_30 ?? 0);
        $lateOver30 = (int) ($rows->late_over_30 ?? 0);

        return [
            'current' => $current,
            'late_30' => $late30,
            'late_over_30' => $lateOver30,
            'total' => $current + $late30 + $lateOver30,
        ];
    }

    /**
     * Cash received per month for the last six months, oldest first, with
     * empty months included as zero so the sparkline keeps its shape.
     *
     * @return list<array{month: string, label: string, revenue: int}>
     */
    private function revenueTrend(): array
    {
        $start = Carbon::now()->startOfMonth()->subMonths(self::TREND_MONTHS - 1);

        $byMonth = Payment::query()
            ->where('paid_at', '>=', $start)
            ->selectRaw("to_char(paid_at, 'YYYY-MM') as ym, SUM(amount) as revenue")
            ->groupBy('ym')
            ->pluck('revenue', 'ym');

        $months = [];

        for ($i = 0; $i < self::TREND_MONTHS; $i++) {
            $month = $start->copy()->addMonths($i);
            $key = $month->format('Y-m');

            $months[] = [
                'month' => $key,
                'label' => $month->format('M'),
                'revenue' => (int) ($byMonth[$key] ?? 0),
            ];
        }

        return $months;
    }

    /** Cash received in a window. */
    private function paymentsBetween(Carbon $from, Carbon $to): int
    {
        return (int) Payment::query()
            ->whereBetween('paid_at', [$from, $to])
            ->sum('amount');
    }

    // ── attention groups ────────────────────────────────────────────────────

    /** Unpaid invoices past their due date. The only group that states money. */
    private function overdueInvoices(): array
    {
        $today = Carbon::today();

        $invoices = Invoice::query()
            ->with('customer:id,name')
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->orderBy('due_date')
            ->limit(self::ATTENTION_LIMIT)
            ->get(['id', 'customer_id', 'due_date', 'total', 'paid_amount']);

        $count = Invoice::query()
            ->whereNotIn('status', [Invoice::STATUS_PAID, Invoice::STATUS_CANCELLED])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->count();

        return [
            'key' => 'overdue_invoices',
            'severity' => self::SEVERITY_URGENT,
            'count' => $count,
            'items' => $invoices->map(fn (Invoice $i) => [
                'id' => (int) $i->id,
                'label' => $i->customer?->name ?? '—',
                'detail_key' => 'days_overdue',
                // abs(): the due date is in the past, and Carbon's diff is
                // signed — without this the UI reads "-40 days overdue".
                'detail_value' => (int) abs($today->diffInDays(Carbon::parse($i->due_date))),
                'amount' => (int) $i->total - (int) $i->paid_amount,
                'url' => "invoices/{$i->id}",
            ])->all(),
        ];
    }

    /** Registration, insurance or service due soon or already past. */
    private function fleetExpiries(): array
    {
        $vehicles = Vehicle::query()
            ->expiringSoon(['registration_expiry', 'insurance_expiry', 'next_service_due'])
            ->orderBy('registration_expiry')
            ->get(['id', 'registration_number', 'make', 'model', 'registration_expiry',
                'insurance_expiry', 'next_service_due', 'next_service_km', 'current_odometer']);

        $items = [];
        $overdue = 0;

        foreach ($vehicles as $vehicle) {
            $worst = $this->worstExpiry($vehicle);

            if ($worst === null) {
                continue;
            }

            if ($worst['state'] === Vehicle::EXPIRY_OVERDUE) {
                $overdue++;
            }

            $items[] = [
                'id' => (int) $vehicle->id,
                'label' => $vehicle->registration_number,
                'detail_key' => $worst['what'],
                'detail_value' => $worst['due'],
                'overdue' => $worst['state'] === Vehicle::EXPIRY_OVERDUE,
                'url' => "fleet/{$vehicle->id}",
            ];
        }

        // Overdue first, then due-soon; each keeps its date order.
        usort($items, fn ($a, $b) => ($b['overdue'] <=> $a['overdue']));

        return [
            'key' => 'fleet_expiries',
            'severity' => $overdue > 0 ? self::SEVERITY_URGENT : self::SEVERITY_SOON,
            'count' => count($items),
            'items' => array_slice($items, 0, self::ATTENTION_LIMIT),
        ];
    }

    /** Agreements finishing within the fortnight — a vehicle to collect back. */
    private function agreementsEnding(): array
    {
        $today = Carbon::today();
        $cutoff = $today->copy()->addDays(self::ENDING_SOON_DAYS);

        $query = Agreement::query()
            ->whereIn('status', [Agreement::STATUS_SIGNED, Agreement::STATUS_ACTIVE])
            ->whereNotNull('end_date')
            ->whereBetween('end_date', [$today, $cutoff]);

        $agreements = (clone $query)
            ->with(['customer:id,name', 'vehicle:id,registration_number'])
            ->orderBy('end_date')
            ->limit(self::ATTENTION_LIMIT)
            ->get(['id', 'customer_id', 'vehicle_id', 'end_date']);

        return [
            'key' => 'agreements_ending',
            'severity' => self::SEVERITY_SOON,
            'count' => $query->count(),
            'items' => $agreements->map(fn (Agreement $a) => [
                'id' => (int) $a->id,
                'label' => $a->customer?->name ?? '—',
                'detail_key' => 'vehicle',
                'detail_value' => $a->vehicle?->registration_number ?? '—',
                'date' => Carbon::parse($a->end_date)->toDateString(),
                'url' => "agreements/{$a->id}",
            ])->all(),
        ];
    }

    /** Someone filled in the form and nobody has turned them into a customer. */
    private function unconvertedLeads(): array
    {
        $query = Lead::query()
            ->whereIn('status', [Lead::STATUS_NEW, Lead::STATUS_CONTACTED])
            ->whereNotNull('submitted_at');

        $leads = (clone $query)
            ->orderByDesc('submitted_at')
            ->limit(self::ATTENTION_LIMIT)
            ->get(['id', 'name', 'phone', 'submitted_at']);

        return [
            'key' => 'unconverted_leads',
            'severity' => self::SEVERITY_SOON,
            'count' => $query->count(),
            'items' => $leads->map(fn (Lead $l) => [
                'id' => (int) $l->id,
                'label' => $l->name ?: '—',
                'detail_key' => 'phone',
                'detail_value' => $l->phone ?: '—',
                'date' => $l->submitted_at?->toDateString(),
                'url' => "leads/{$l->id}",
            ])->all(),
        ];
    }

    /** Vehicles earning nothing: in an accident, or suspended. */
    private function vehiclesOffRoad(): array
    {
        $statuses = [Vehicle::STATUS_ACCIDENT, Vehicle::STATUS_SUSPENDED];

        $query = Vehicle::query()->whereIn('status', $statuses);

        $vehicles = (clone $query)
            ->orderBy('registration_number')
            ->limit(self::ATTENTION_LIMIT)
            ->get(['id', 'registration_number', 'status']);

        return [
            'key' => 'vehicles_off_road',
            'severity' => self::SEVERITY_SOON,
            'count' => $query->count(),
            'items' => $vehicles->map(fn (Vehicle $v) => [
                'id' => (int) $v->id,
                'label' => $v->registration_number,
                'detail_key' => 'status',
                'detail_value' => $v->status,
                'url' => "fleet/{$v->id}",
            ])->all(),
        ];
    }

    /** Workshop jobs stalled on parts or waiting to be re-inspected. */
    private function openWorkshopJobs(): array
    {
        $statuses = [ServiceLog::STATUS_WAITING_FOR_PARTS, ServiceLog::STATUS_RE_INSPECTION_REQUIRED];

        $query = ServiceLog::query()->whereIn('status', $statuses);

        $logs = (clone $query)
            ->with('vehicle:id,registration_number')
            ->orderBy('id')
            ->limit(self::ATTENTION_LIMIT)
            ->get(['id', 'vehicle_id', 'status']);

        return [
            'key' => 'workshop_jobs',
            'severity' => self::SEVERITY_SOON,
            'count' => $query->count(),
            'items' => $logs->map(fn (ServiceLog $s) => [
                'id' => (int) $s->id,
                'label' => $s->vehicle?->registration_number ?? '—',
                'detail_key' => 'status',
                'detail_value' => $s->status,
                'url' => "workshop/{$s->id}",
            ])->all(),
        ];
    }

    /**
     * The most urgent of a vehicle's three dates plus its service-by-km state,
     * or null when nothing is actionable.
     *
     * @return array{what: string, due: string, state: string}|null
     */
    private function worstExpiry(Vehicle $vehicle): ?array
    {
        $candidates = [];

        $dates = [
            'registration_expiry' => 'registration',
            'insurance_expiry' => 'insurance',
            'next_service_due' => 'service',
        ];

        foreach ($dates as $column => $what) {
            $state = $vehicle->expiryState($column);

            if (in_array($state, [Vehicle::EXPIRY_OVERDUE, Vehicle::EXPIRY_DUE_SOON], true)) {
                $candidates[] = [
                    'what' => $what,
                    'due' => Carbon::parse($vehicle->{$column})->toDateString(),
                    'state' => $state,
                    'rank' => $state === Vehicle::EXPIRY_OVERDUE ? 2 : 1,
                ];
            }
        }

        $kmState = $vehicle->serviceKmState();

        if (in_array($kmState, [Vehicle::EXPIRY_OVERDUE, Vehicle::EXPIRY_DUE_SOON], true)) {
            $candidates[] = [
                'what' => 'service_km',
                'due' => number_format((int) $vehicle->next_service_km).' km',
                'state' => $kmState,
                'rank' => $kmState === Vehicle::EXPIRY_OVERDUE ? 2 : 1,
            ];
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn ($a, $b) => $b['rank'] <=> $a['rank']);

        return ['what' => $candidates[0]['what'], 'due' => $candidates[0]['due'], 'state' => $candidates[0]['state']];
    }

    /** Whether this user may be shown money at all. */
    public function seesMoney(?TenantUser $user): bool
    {
        return $user !== null && in_array(
            $user->role,
            [TenantUser::ROLE_ADMIN, TenantUser::ROLE_ACCOUNTS],
            true,
        );
    }
}
