<?php

namespace App\Domain\Analytics\Http\Controllers;

use App\Domain\Analytics\Application\CreateSavedReport;
use App\Domain\Analytics\Application\DeleteSavedReport;
use App\Domain\Analytics\Application\UpdateSavedReport;
use App\Domain\Analytics\Http\Requests\StoreSavedReportRequest;
use App\Domain\Analytics\Http\Requests\UpdateSavedReportRequest;
use App\Domain\Analytics\Http\Resources\SavedReportResource;
use App\Domain\Analytics\Models\SavedReport;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class SavedReportController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SavedReportResource::collection(SavedReport::query()->orderBy('name')->get());
    }

    public function store(StoreSavedReportRequest $request, CreateSavedReport $action): JsonResponse
    {
        return (new SavedReportResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateSavedReportRequest $request, SavedReport $savedReport, UpdateSavedReport $action): SavedReportResource
    {
        return new SavedReportResource($action->handle($savedReport, $request->validated()));
    }

    public function destroy(SavedReport $savedReport, DeleteSavedReport $action): Response
    {
        $action->handle($savedReport);

        return response()->noContent();
    }
}
