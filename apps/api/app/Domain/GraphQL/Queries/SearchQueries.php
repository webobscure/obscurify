<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Search\Application\ExecuteSearch;
use App\Domain\Search\Enums\SearchSortOption;
use App\Domain\Search\Support\ExecuteSearchRequest;
use App\Domain\Search\Support\SearchFilters;
use App\Domain\Search\Support\SearchSuggestionEngine;
use GraphQL\Type\Definition\Type;

/**
 * `search`/`searchSuggestions` — call ExecuteSearch/SearchSuggestionEngine
 * exactly as StorefrontSearchController does (Milestone 22); no
 * search-specific logic lives in this resolver, only argument mapping.
 * `filters` is a JSON scalar accepting the same shape
 * `SearchFilters::fromArray()` already parses from REST's query string
 * (`category_ids`, `collection_ids`, `vendors`, `product_types`, `tags`,
 * `variant_options`, `price_min`, `price_max`, `availability`).
 */
final class SearchQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('search', [
            'type' => $types->get('SearchResult'),
            'args' => [
                'query' => Type::string(),
                'filters' => $types->get('JSON'),
                'sort' => Type::string(),
                'page' => Type::int(),
                'perPage' => Type::int(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $result = app(ExecuteSearch::class)->handle($context->store, new ExecuteSearchRequest(
                    queryText: $args['query'] ?? '',
                    filters: SearchFilters::fromArray($args['filters'] ?? []),
                    sort: SearchSortOption::tryFrom((string) ($args['sort'] ?? '')) ?? SearchSortOption::Relevance,
                    page: max(1, $args['page'] ?? 1),
                    perPage: min(100, max(1, $args['perPage'] ?? 24)),
                    customerId: $context->customer?->id,
                ));

                return [
                    'items' => $result->items,
                    'total' => $result->total,
                    'page' => $result->page,
                    'perPage' => $result->perPage,
                    'facets' => $result->facets,
                    'searchQueryId' => $result->searchQuery->id,
                ];
            },
        ]);

        $queries->register('searchSuggestions', [
            'type' => $types->get('SearchSuggestions'),
            'args' => ['query' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $suggestions = app(SearchSuggestionEngine::class)->suggest($context->store, $args['query']);

                return [
                    'products' => array_map(fn ($p) => ['productId' => $p->productId, 'title' => $p->title, 'thumbnailUrl' => $p->thumbnailUrl], $suggestions['products']),
                    'collections' => array_map(fn ($c) => ['id' => $c['id'], 'title' => $c['title']], $suggestions['collections']),
                    'categories' => array_map(fn ($c) => ['id' => $c['id'], 'title' => $c['title']], $suggestions['categories']),
                    'popularQueries' => $suggestions['popular_queries'],
                ];
            },
        ]);
    }
}
