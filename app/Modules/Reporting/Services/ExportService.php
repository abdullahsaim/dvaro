<?php

namespace App\Modules\Reporting\Services;

use App\Services\BaseService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a report to a file (PDF or Excel) and stores it on S3, returning the
 * S3 path. CLAUDE.md: exports are ALWAYS queued — both methods are only ever
 * invoked from GenerateReportExportJob, never inside a web request.
 *
 * PDF uses dompdf (pure PHP, no Chromium — same approach as the agreement /
 * invoice PDFs). Excel uses PhpSpreadsheet.
 *
 * Data shape per report_type (assembled by the job):
 *   revenue  → ['by_period' => [...], 'by_vehicle' => [...]]
 *   fleet    → ['rows' => [...]]
 *   overdue  → ['rows' => [...]]
 *   workshop → ['total_jobs'=>, 'total_labour_cost'=>, ..., 'by_mechanic'=>[...]]
 */
class ExportService extends BaseService
{
    /** Report types that can be exported (each has a Blade view + tabulariser). */
    public const TYPES = ['revenue', 'fleet', 'overdue', 'workshop'];

    /**
     * Render the report-specific Blade view to a PDF and store it on the
     * default (sensitive) disk — 'local' (private) by default, 's3' once
     * configured. Served back only through the auth-checked download endpoint.
     */
    public function exportPdf(string $reportType, array $data, string $title): string
    {
        $tenant = app('current_tenant');

        $pdf = Pdf::loadView("reports.{$reportType}", [
            'title' => $title,
            'data' => $data,
            'tenant' => $tenant,
            'generatedAt' => now($this->timezone($tenant)),
        ]);

        $path = $this->path($tenant->id, $reportType, 'pdf');
        Storage::disk(config('filesystems.default'))->put($path, $pdf->output());

        return $path;
    }

    /**
     * Build an .xlsx from the report's tabular form and store it on the default
     * (sensitive) disk — 'local' (private) by default, 's3' once configured.
     */
    public function exportExcel(string $reportType, array $data, string $title): string
    {
        $tenant = app('current_tenant');
        $table = $this->tabulate($reportType, $data);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        // Title row.
        $sheet->setCellValue('A1', $title);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        // Header row.
        $headerRow = 3;
        foreach ($table['columns'] as $i => $heading) {
            $cell = $this->columnLetter($i).$headerRow;
            $sheet->setCellValue($cell, $heading);
            $sheet->getStyle($cell)->getFont()->setBold(true);
        }

        // Data rows.
        $rowNum = $headerRow + 1;
        foreach ($table['rows'] as $row) {
            foreach (array_values($row) as $i => $value) {
                $cell = $this->columnLetter($i).$rowNum;
                $sheet->setCellValue($cell, $value);
                if (is_numeric($value)) {
                    $sheet->getStyle($cell)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
            }
            $rowNum++;
        }

        foreach (array_keys($table['columns']) as $i) {
            $sheet->getColumnDimension($this->columnLetter($i))->setAutoSize(true);
        }

        // Write to a temp file, then push the bytes to S3.
        $tmp = tempnam(sys_get_temp_dir(), 'dvaro-report-');
        (new Xlsx($spreadsheet))->save($tmp);

        $path = $this->path($tenant->id, $reportType, 'xlsx');
        Storage::disk(config('filesystems.default'))->put($path, file_get_contents($tmp));

        @unlink($tmp);

        return $path;
    }

    /**
     * Flatten a report into columns + string rows for the Excel writer. Money is
     * formatted from cents to a plain "1234.56" string (AUD, no symbol — the
     * column heading names the currency).
     *
     * @return array{columns: list<string>, rows: list<list<scalar>>}
     */
    private function tabulate(string $reportType, array $data): array
    {
        return match ($reportType) {
            'revenue' => [
                'columns' => ['Month', 'Revenue (AUD)'],
                'rows' => array_map(
                    fn ($r) => [$r['month'], $this->aud($r['revenue'])],
                    $data['by_period'] ?? [],
                ),
            ],
            'fleet' => [
                'columns' => ['Vehicle', 'Total days', 'Days rented', 'Utilisation %'],
                'rows' => array_map(
                    fn ($r) => [$r['vehicle'], $r['total_days'], $r['days_rented'], $r['utilisation']],
                    $data['rows'] ?? [],
                ),
            ],
            'overdue' => [
                'columns' => ['Customer', 'Invoice total (AUD)', 'Outstanding (AUD)', 'Days overdue'],
                'rows' => array_map(
                    fn ($r) => [$r['customer'], $this->aud($r['total']), $this->aud($r['outstanding']), $r['days_overdue']],
                    $data['rows'] ?? [],
                ),
            ],
            'workshop' => [
                'columns' => ['Mechanic', 'Jobs', 'Labour (AUD)', 'Parts (AUD)', 'Total (AUD)'],
                'rows' => array_map(
                    fn ($r) => [$r['mechanic'], $r['jobs'], $this->aud($r['labour_cost']), $this->aud($r['parts_cost']), $this->aud($r['total_cost'])],
                    $data['by_mechanic'] ?? [],
                ),
            ],
            default => ['columns' => [], 'rows' => []],
        };
    }

    private function path(int $tenantId, string $reportType, string $ext): string
    {
        return "tenants/{$tenantId}/reports/{$reportType}-".now()->format('Y-m-d-His').'.'.$ext;
    }

    private function aud(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    private function columnLetter(int $index): string
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
    }

    private function timezone(object $tenant): string
    {
        return $tenant->settings['timezone'] ?? 'Australia/Sydney';
    }
}
