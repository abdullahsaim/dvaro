<?php

namespace App\Modules\Reporting\Http\Requests;

use App\Modules\Reporting\Models\ReportExport;
use App\Modules\Reporting\Services\ExportService;
use Illuminate\Validation\Rule;

/**
 * Validates an export request: a report type, an output format and the same
 * optional date range as ReportRequest (inherited).
 *
 * The 5-per-hour-per-tenant rate limit is NOT enforced here — it is applied as a
 * named route limiter ('report-export', keyed by tenant_id) on the POST route,
 * the idiomatic place for throttling. See AppServiceProvider + routes/tenant.php.
 */
class ExportRequest extends ReportRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'report_type' => ['required', Rule::in(ExportService::TYPES)],
            'format' => ['required', Rule::in([ReportExport::FORMAT_PDF, ReportExport::FORMAT_EXCEL])],
        ];
    }
}
