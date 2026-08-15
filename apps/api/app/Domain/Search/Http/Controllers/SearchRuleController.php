<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\CreateSearchRule;
use App\Domain\Search\Application\DeleteSearchRule;
use App\Domain\Search\Application\UpdateSearchRule;
use App\Domain\Search\Http\Requests\StoreSearchRuleRequest;
use App\Domain\Search\Http\Requests\UpdateSearchRuleRequest;
use App\Domain\Search\Http\Resources\SearchRuleResource;
use App\Domain\Search\Models\SearchRule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class SearchRuleController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SearchRuleResource::collection(SearchRule::query()->orderBy('position')->get());
    }

    public function store(StoreSearchRuleRequest $request, CreateSearchRule $action): JsonResponse
    {
        return (new SearchRuleResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateSearchRuleRequest $request, SearchRule $searchRule, UpdateSearchRule $action): SearchRuleResource
    {
        return new SearchRuleResource($action->handle($searchRule, $request->validated()));
    }

    public function destroy(SearchRule $searchRule, DeleteSearchRule $action): Response
    {
        $action->handle($searchRule);

        return response()->noContent();
    }
}
