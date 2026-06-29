<?php

namespace App\Modules\Reporting\Events;

use App\Modules\Reporting\Models\ReportExport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when GenerateReportExportJob has finished generating a report file and
 * stored it on S3. Carries the (ready) ReportExport.
 *
 * No listeners yet — notifying the requester that their export is ready
 * (email/in-app) attaches in a future Notification session. Listener-less, so
 * it is NOT registered in EventServiceProvider.
 */
class ReportExportReady
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly ReportExport $export,
    ) {}
}
