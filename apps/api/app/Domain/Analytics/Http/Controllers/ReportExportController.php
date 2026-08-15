<?php

namespace App\Domain\Analytics\Http\Controllers;

use App\Domain\Analytics\Application\ExportReport;
use App\Domain\Analytics\Enums\ExportFormat;
use App\Domain\Analytics\Http\Requests\StoreReportExportRequest;
use App\Domain\Analytics\Http\Resources\ReportExportResource;
use App\Domain\Analytics\Models\Report;
use App\Domain\Analytics\Models\ReportExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ReportExportController extends Controller
{
    private const array CONTENT_TYPES = [
        'csv' => 'text/csv',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pdf' => 'application/pdf',
    ];

    public function store(StoreReportExportRequest $request, Report $report, ExportReport $action): JsonResponse
    {
        $data = $request->validated();

        $export = $action->handle(
            $report,
            ExportFormat::from($data['format']),
            $data['scheduled_at'] ?? null,
            $data['recurrence'] ?? null,
        );

        return (new ReportExportResource($export))->response()->setStatusCode(201);
    }

    public function download(ReportExport $export): StreamedResponse|Response
    {
        if ($export->file_path === null || ! Storage::disk('local')->exists($export->file_path)) {
            return response('Export file not found.', 404);
        }

        $contentType = self::CONTENT_TYPES[$export->format->value];
        $filename = "report-{$export->report_id}.{$export->format->value}";

        return Storage::disk('local')->download($export->file_path, $filename, ['Content-Type' => $contentType]);
    }
}
