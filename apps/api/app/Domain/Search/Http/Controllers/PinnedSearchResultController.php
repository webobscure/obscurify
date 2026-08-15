<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\CreatePinnedSearchResult;
use App\Domain\Search\Application\DeletePinnedSearchResult;
use App\Domain\Search\Application\UpdatePinnedSearchResult;
use App\Domain\Search\Http\Requests\StorePinnedSearchResultRequest;
use App\Domain\Search\Http\Requests\UpdatePinnedSearchResultRequest;
use App\Domain\Search\Http\Resources\PinnedSearchResultResource;
use App\Domain\Search\Models\PinnedSearchResult;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PinnedSearchResultController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PinnedSearchResultResource::collection(PinnedSearchResult::query()->orderBy('keyword')->orderBy('position')->get());
    }

    public function store(StorePinnedSearchResultRequest $request, CreatePinnedSearchResult $action): JsonResponse
    {
        return (new PinnedSearchResultResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdatePinnedSearchResultRequest $request, PinnedSearchResult $pinnedSearchResult, UpdatePinnedSearchResult $action): PinnedSearchResultResource
    {
        return new PinnedSearchResultResource($action->handle($pinnedSearchResult, $request->validated()));
    }

    public function destroy(PinnedSearchResult $pinnedSearchResult, DeletePinnedSearchResult $action): Response
    {
        $action->handle($pinnedSearchResult);

        return response()->noContent();
    }
}
