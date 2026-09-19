<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Expense;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

/**
 * Expense aggregations (tenant-scoped via HasTenant; VOIDED EXPENSES EXCLUDED)
 * for the Expenses page summary cards, the Reporting "Expenses" report + its
 * PDF/Excel export, and profit per vehicle. Money in CENTS; ranges are
 * inclusive calendar dates on expense_date.
 */
class ExpenseReportService extends BaseService
{
    /**
     * @return array{
     *     totals: array{count:int, total:int, gst:int, ex_gst:int},
     *     by_category: list<array{category:string, count:int, total:int, gst:int, ex_gst:int}>,
     *     by_month: list<array{month:string, total:int, gst:int, ex_gst:int}>
     * }
     */
    public function summary(Carbon $from, Carbon $to, ?int $categoryId = null, ?int $vehicleId = null): array
    {
        $base = fn (): Builder => Expense::query()
            ->active()
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($vehicleId, fn ($q) => $q->where('vehicle_id', $vehicleId));

        $totals = $base()
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(amount_total),0) as total, COALESCE(SUM(gst_amount),0) as gst, COALESCE(SUM(amount_ex_gst),0) as ex')
            ->first();

        $byCategory = $base()
            ->join('expense_categories', 'expense_categories.id', '=', 'expenses.expense_category_id')
            ->groupBy('expense_categories.id', 'expense_categories.name')
            ->selectRaw('expense_categories.name as category, COUNT(*) as n, SUM(amount_total) as total, SUM(gst_amount) as gst, SUM(amount_ex_gst) as ex')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category,
                'count' => (int) $r->n,
                'total' => (int) $r->total,
                'gst' => (int) $r->gst,
                'ex_gst' => (int) $r->ex,
            ])
            ->all();

        $byMonth = $base()
            ->selectRaw("to_char(expense_date, 'YYYY-MM') as ym, SUM(amount_total) as total, SUM(gst_amount) as gst, SUM(amount_ex_gst) as ex")
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->ym,
                'total' => (int) $r->total,
                'gst' => (int) $r->gst,
                'ex_gst' => (int) $r->ex,
            ])
            ->all();

        return [
            'totals' => [
                'count' => (int) $totals->n,
                'total' => (int) $totals->total,
                'gst' => (int) $totals->gst,
                'ex_gst' => (int) $totals->ex,
            ],
            'by_category' => $byCategory,
            'by_month' => $byMonth,
        ];
    }

    /**
     * Active expense totals per vehicle in the window (cents), keyed by
     * vehicle_id — feeds profit per vehicle.
     *
     * @return array<int, int>
     */
    public function totalsByVehicle(Carbon $from, Carbon $to): array
    {
        return Expense::query()
            ->active()
            ->whereNotNull('vehicle_id')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('vehicle_id')
            ->selectRaw('vehicle_id, SUM(amount_total) as total')
            ->pluck('total', 'vehicle_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
