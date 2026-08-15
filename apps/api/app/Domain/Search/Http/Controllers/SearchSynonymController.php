<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\CreateSearchSynonym;
use App\Domain\Search\Application\DeleteSearchSynonym;
use App\Domain\Search\Application\UpdateSearchSynonym;
use App\Domain\Search\Http\Requests\StoreSearchSynonymRequest;
use App\Domain\Search\Http\Requests\UpdateSearchSynonymRequest;
use App\Domain\Search\Http\Resources\SearchSynonymResource;
use App\Domain\Search\Models\SearchSynonym;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class SearchSynonymController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SearchSynonymResource::collection(SearchSynonym::query()->orderBy('term')->get());
    }

    public function store(StoreSearchSynonymRequest $request, CreateSearchSynonym $action): JsonResponse
    {
        return (new SearchSynonymResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateSearchSynonymRequest $request, SearchSynonym $searchSynonym, UpdateSearchSynonym $action): SearchSynonymResource
    {
        return new SearchSynonymResource($action->handle($searchSynonym, $request->validated()));
    }

    public function destroy(SearchSynonym $searchSynonym, DeleteSearchSynonym $action): Response
    {
        $action->handle($searchSynonym);

        return response()->noContent();
    }
}
