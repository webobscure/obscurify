<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Enums\SearchSortOption;
use App\Domain\Search\Http\Resources\SearchResultResource;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchFilters;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Backs the admin Search Dashboard's "try a search" preview — lets a
 * merchant see exactly what a customer would (merchandising rules,
 * synonyms, ranking included), without leaving the admin.
 */
final class SearchPreviewController extends Controller
{
    public function show(Request $request, TenantContext $tenantContext, ExecuteSearch $executeSearch): JsonResponse
    {
        $result = $executeSearch->handle($tenantContext->store(), new ExecuteSearchRequest(
            queryText: $request->string('q')->toString(),
            filters: SearchFilters::fromArray($request->input('filters', [])),
            sort: SearchSortOption::tryFrom((string) $request->string('sort')) ?? SearchSortOption::Relevance,
            page: max(1, $request->integer('page', 1)),
            perPage: min(100, max(1, $request->integer('per_page', 24))),
        ));

        return response()->json(SearchResultResource::toArray($result));
    }
}
