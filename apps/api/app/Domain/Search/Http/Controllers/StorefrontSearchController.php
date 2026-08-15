<?php

namespace App\Domain\Search\Http\Controllers;

use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Application\RecordSearchClick;
use App\Domain\Search\Application\RecordSearchConversion;
use App\Domain\Search\Enums\SearchSortOption;
use App\Domain\Search\Http\Requests\StoreSearchClickRequest;
use App\Domain\Search\Http\Requests\StoreSearchConversionRequest;
use App\Domain\Search\Http\Resources\SearchResultResource;
use App\Domain\Search\Models\SearchQuery as SearchQueryModel;
use App\Domain\Search\Models\SearchSettings;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchFilters;
use App\Domain\Search\Support\SearchSuggestionEngine;
use App\Http\Controllers\Controller;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Spec section 14 — every response here is provider-independent
 * (SearchResultResource/SearchSuggestionEngine read only
 * SearchProviderContract's own return shapes, never a provider-specific
 * one). `POST /search/reindex` is deliberately NOT exposed here despite
 * appearing under spec section 14's own "Storefront API" heading — see
 * SearchIndexController's docblock and docs/adr/028-search-platform.md.
 */
final class StorefrontSearchController extends Controller
{
    public function index(Request $request, TenantContext $tenantContext, ExecuteSearch $executeSearch): JsonResponse
    {
        $settings = $this->resolveSettings($tenantContext);

        $result = $executeSearch->handle($tenantContext->store(), new ExecuteSearchRequest(
            queryText: $request->string('q')->toString(),
            filters: SearchFilters::fromArray($request->input('filters', [])),
            sort: SearchSortOption::tryFrom((string) $request->string('sort')) ?? SearchSortOption::Relevance,
            page: max(1, $request->integer('page', 1)),
            perPage: min(100, max(1, $request->integer('per_page', $settings['results_per_page'] ?? 24))),
            sessionId: $request->string('session_id')->toString() ?: null,
        ));

        return response()->json(SearchResultResource::toArray($result));
    }

    public function suggestions(Request $request, TenantContext $tenantContext, SearchSuggestionEngine $engine): JsonResponse
    {
        return response()->json(['data' => $engine->suggest($tenantContext->store(), $request->string('q')->toString())]);
    }

    /**
     * A facets-only view of the same search — reuses ExecuteSearch
     * entirely rather than a second query path, so facet counts here
     * can never drift from what GET /search itself would show for the
     * identical query/filters.
     */
    public function facets(Request $request, TenantContext $tenantContext, ExecuteSearch $executeSearch): JsonResponse
    {
        $result = $executeSearch->handle($tenantContext->store(), new ExecuteSearchRequest(
            queryText: $request->string('q')->toString(),
            filters: SearchFilters::fromArray($request->input('filters', [])),
            perPage: 1,
        ));

        return response()->json(['facets' => $result->facets]);
    }

    public function popular(TenantContext $tenantContext, SearchSuggestionEngine $engine): JsonResponse
    {
        return response()->json(['data' => $engine->suggest($tenantContext->store(), '')['popular_queries']]);
    }

    public function click(StoreSearchClickRequest $request, RecordSearchClick $action): JsonResponse
    {
        $data = $request->validated();
        $searchQuery = isset($data['search_query_id']) ? SearchQueryModel::query()->find($data['search_query_id']) : null;

        $action->handle($searchQuery, $data['product_id'], $data['position'] ?? null);

        return response()->json(['data' => ['recorded' => true]], 201);
    }

    public function conversions(StoreSearchConversionRequest $request, RecordSearchConversion $action): JsonResponse
    {
        $data = $request->validated();
        $searchQuery = SearchQueryModel::query()->findOrFail($data['search_query_id']);

        $action->handle($searchQuery, $data['product_id'], $data['order_id']);

        return response()->json(['data' => ['recorded' => true]], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveSettings(TenantContext $tenantContext): array
    {
        $settings = SearchSettings::query()->where('store_id', $tenantContext->storeId())->first();

        return $settings !== null ? ['results_per_page' => $settings->results_per_page] : [];
    }
}
