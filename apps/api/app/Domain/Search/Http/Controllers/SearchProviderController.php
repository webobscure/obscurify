<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\CreateSearchProvider;
use App\Domain\Search\Application\DeleteSearchProvider;
use App\Domain\Search\Application\EnsureDefaultSearchSetup;
use App\Domain\Search\Application\UpdateSearchProvider;
use App\Domain\Search\Http\Requests\StoreSearchProviderRequest;
use App\Domain\Search\Http\Requests\UpdateSearchProviderRequest;
use App\Domain\Search\Http\Resources\SearchProviderResource;
use App\Domain\Search\Models\SearchProvider;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class SearchProviderController extends Controller
{
    public function index(TenantContext $tenantContext, EnsureDefaultSearchSetup $ensureDefaults): AnonymousResourceCollection
    {
        $ensureDefaults->handle($tenantContext->store());

        return SearchProviderResource::collection(SearchProvider::query()->orderBy('name')->get());
    }

    public function store(StoreSearchProviderRequest $request, CreateSearchProvider $action): JsonResponse
    {
        return (new SearchProviderResource($action->handle($request->validated())))->response()->setStatusCode(201);
    }

    public function update(UpdateSearchProviderRequest $request, SearchProvider $searchProvider, UpdateSearchProvider $action): SearchProviderResource
    {
        return new SearchProviderResource($action->handle($searchProvider, $request->validated()));
    }

    public function destroy(SearchProvider $searchProvider, DeleteSearchProvider $action): Response
    {
        $action->handle($searchProvider);

        return response()->noContent();
    }
}
