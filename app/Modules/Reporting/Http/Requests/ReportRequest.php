<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Services\ReportingService;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates an optional report date range. Authorization is handled by
 * ReportingPolicy in the controller (against the tenant guard).
 *
 * Both dates are optional: when absent they default to the current Australian
 * financial year (1 July – 30 June). When supplied, `from` must precede `to`
 * and the span may not exceed two years.
 */
class ReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled('from') || ! $this->filled('to')) {
                return;
            }

            $from = Carbon::parse($this->date('from'));
            $to = Carbon::parse($this->date('to'));

            if ($from->greaterThanOrEqualTo($to)) {
                $validator->errors()->add('from', __('reporting.range_order'));

                return;
            }

            if ($from->copy()->addYears(2)->lessThan($to)) {
                $validator->errors()->add('to', __('reporting.range_too_long'));
            }
        });
    }

    /**
     * Resolve the effective [from, to] range — supplied values, or the current
     * Australian FY by default.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $fy = app(ReportingService::class)->australianFY();

        $from = $this->filled('from')
            ? Carbon::parse($this->date('from'))->startOfDay()
            : $fy['from'];

        $to = $this->filled('to')
            ? Carbon::parse($this->date('to'))->endOfDay()
            : $fy['to'];

        return [$from, $to];
    }
}
