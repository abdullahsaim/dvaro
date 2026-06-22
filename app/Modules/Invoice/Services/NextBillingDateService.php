<?php

namespace App\Modules\Invoice\Services;

use App\Modules\Agreement\Models\Agreement;
use App\Services\BaseService;
use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Computes the next billing date for an agreement from a given reference date.
 *
 * PURE date logic — NO database access, fully unit-testable. Given an agreement's
 * billing_cycle (+ billing_cycle_day) and a "from" date, it returns the next date
 * on which an invoice should be raised.
 *
 *   daily   → fromDate + 1 day.
 *   weekly  → the next occurrence of billing_cycle_day (a weekday name, e.g.
 *             "wednesday") strictly after fromDate. Falls back to fromDate + 1
 *             week when no day is configured.
 *   monthly → the same day-of-month as billing_cycle_day (e.g. "15") in the
 *             following month, clamped to that month's length (e.g. a "31" in
 *             February lands on the 28th/29th). Falls back to fromDate's own
 *             day-of-month when none is configured.
 *
 * All arithmetic is on whole days — no fractional/float dates.
 */
class NextBillingDateService extends BaseService
{
    /** Weekday name → Carbon ISO day-of-week constant. */
    private const WEEKDAYS = [
        'sunday' => Carbon::SUNDAY,
        'monday' => Carbon::MONDAY,
        'tuesday' => Carbon::TUESDAY,
        'wednesday' => Carbon::WEDNESDAY,
        'thursday' => Carbon::THURSDAY,
        'friday' => Carbon::FRIDAY,
        'saturday' => Carbon::SATURDAY,
    ];

    public function calculate(Agreement $agreement, Carbon $fromDate): Carbon
    {
        // Work on a copy at the start of the day so time-of-day never skews the
        // result; callers only care about the date.
        $from = $fromDate->copy()->startOfDay();

        return match ($agreement->billing_cycle) {
            Agreement::BILLING_DAILY => $from->addDay(),
            Agreement::BILLING_WEEKLY => $this->nextWeekly($from, $agreement->billing_cycle_day),
            Agreement::BILLING_MONTHLY => $this->nextMonthly($from, $agreement->billing_cycle_day),
            default => throw new InvalidArgumentException(
                "Unknown billing cycle [{$agreement->billing_cycle}]."
            ),
        };
    }

    /**
     * Next occurrence of the named weekday strictly after $from. When no weekday
     * is configured, simply advance one week from $from.
     */
    private function nextWeekly(Carbon $from, ?string $day): Carbon
    {
        $key = strtolower(trim((string) $day));

        if (! array_key_exists($key, self::WEEKDAYS)) {
            return $from->copy()->addWeek();
        }

        // Carbon::next() always returns a date strictly AFTER the current one.
        return $from->copy()->next(self::WEEKDAYS[$key]);
    }

    /**
     * The configured day-of-month in the following month, clamped to that
     * month's length. Falls back to $from's own day-of-month.
     */
    private function nextMonthly(Carbon $from, ?string $day): Carbon
    {
        $target = is_numeric($day) ? (int) $day : $from->day;

        // Start of next month, then set the (clamped) day so e.g. a "31" never
        // overflows a short month into the month after.
        $nextMonth = $from->copy()->startOfMonth()->addMonth();
        $clamped = min(max($target, 1), $nextMonth->daysInMonth);

        return $nextMonth->setDay($clamped);
    }
}
