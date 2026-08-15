<?php

namespace App\Domain\Analytics\Http\Controllers;

use App\Domain\Analytics\Application\RunReport;
use App\Domain\Analytics\Enums\ReportType;
use App\Domain\Analytics\Http\Requests\StoreReportRequest;
use App\Domain\Analytics\Http\Resources\ReportResource;
use App\Domain\Analytics\Http\Resources\ReportSummaryResource;
use App\Domain\Analytics\Models\Report;
use App\Domain\Analytics\Models\SavedReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ReportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ReportSummaryResource::collection(Report::query()->orderByDesc('created_at')->paginate());
    }

    public function store(StoreReportRequest $request, RunReport $action): JsonResponse
    {
        $data = $request->validated();
        $savedReport = isset($data['saved_report_id']) ? SavedReport::query()->find($data['saved_report_id']) : null;

        $report = $action->handle(
            ReportType::from($data['report_type']),
            $data['filters'] ?? [],
            $data['columns'] ?? [],
            $savedReport,
        );

        return (new ReportResource($report))->response()->setStatusCode(201);
    }

    public function show(Report $report): ReportResource
    {
        return new ReportResource($report);
    }
}
