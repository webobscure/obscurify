<?php

namespace App\Domain\Analytics\Application;

use App\Domain\Analytics\Enums\ExportFormat;
use App\Domain\Analytics\Enums\ExportStatus;
use App\Domain\Analytics\Models\Report;
use App\Domain\Analytics\Models\ReportExport;
use App\Domain\Analytics\Support\Export\CsvReportWriter;
use App\Domain\Analytics\Support\Export\PdfReportWriter;
use App\Domain\Analytics\Support\Export\XlsxReportWriter;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Generates a Report's file (spec section 9: CSV/Excel/PDF) — a real,
 * synchronous "export now" when `scheduledAt` is null. Scheduling
 * (`scheduledAt`/`recurrence`) is stored but never automatically
 * dispatched — "scheduled export architecture only," see ReportExport's
 * own docblock and docs/adr/026-analytics-platform.md.
 */
final class ExportReport
{
    public function __construct(
        private readonly CsvReportWriter $csvWriter,
        private readonly XlsxReportWriter $xlsxWriter,
        private readonly PdfReportWriter $pdfWriter,
    ) {}

    public function handle(Report $report, ExportFormat $format, ?string $scheduledAt = null, ?string $recurrence = null): ReportExport
    {
        $export = ReportExport::query()->create([
            'report_id' => $report->id,
            'format' => $format->value,
            'status' => ExportStatus::Pending->value,
            'scheduled_at' => $scheduledAt,
            'recurrence' => $recurrence,
        ]);

        // A scheduled-for-later export is recorded but not generated
        // now — nothing in this milestone dispatches it automatically.
        if ($scheduledAt !== null) {
            return $export;
        }

        try {
            $rows = $report->result ?? [];

            $contents = match ($format) {
                ExportFormat::Csv => $this->csvWriter->write($rows),
                ExportFormat::Xlsx => $this->xlsxWriter->write($rows),
                ExportFormat::Pdf => $this->pdfWriter->write($rows),
            };

            $path = "analytics-exports/{$report->store_id}/{$export->id}.{$format->value}";
            Storage::disk('local')->put($path, $contents);

            $export->update([
                'status' => ExportStatus::Completed->value,
                'file_path' => $path,
                'file_size' => strlen($contents),
                'completed_at' => now(),
            ]);
        } catch (Throwable) {
            $export->update(['status' => ExportStatus::Failed->value]);
        }

        return $export->fresh();
    }
}
