<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\Search\Support\SearchResultItem;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * Search & Discovery (Milestone 22) types, exposed read-only over
 * GraphQL — every query here calls the exact same ExecuteSearch/
 * SearchSuggestionEngine services the REST storefront search endpoints
 * call (see SearchQueries), never SearchDocument directly. `facets` is
 * a JSON scalar rather than a typed object: SearchFacetBuilder's own
 * output shape is deliberately heterogeneous per facet kind (vendor is
 * value+count, category is id+label+count, price is min+max) — see
 * docs/adr/029-graphql-platform.md for why this one field stays
 * schemaless rather than a union of five near-identical object types.
 */
final class SearchTypes
{
    public static function searchResultItem(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'SearchResultItem',
            'fields' => fn () => [
                'productId' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'vendor' => Type::string(),
                'productType' => Type::string(),
                'price' => $types->get('PriceRange'),
                'thumbnailUrl' => Type::string(),
                'availability' => Type::nonNull(Type::boolean()),
                'score' => Type::nonNull(Type::float()),
                'isPinned' => Type::nonNull(Type::boolean()),
            ],
            'resolveField' => function (SearchResultItem $item, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'productId' => $item->productId,
                    'title' => $item->title,
                    'slug' => $item->slug,
                    'description' => $item->description,
                    'vendor' => $item->vendor,
                    'productType' => $item->productType,
                    'price' => ['min' => $item->priceMin, 'max' => $item->priceMax, 'currency' => $item->currency],
                    'thumbnailUrl' => $item->thumbnailUrl,
                    'availability' => $item->availability,
                    'score' => $item->score,
                    'isPinned' => $item->isPinned,
                    default => null,
                };
            },
        ]);
    }

    public static function searchResult(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'SearchResult',
            'fields' => [
                'items' => Type::listOf($types->get('SearchResultItem')),
                'total' => Type::nonNull(Type::int()),
                'page' => Type::nonNull(Type::int()),
                'perPage' => Type::nonNull(Type::int()),
                'facets' => $types->get('JSON'),
                'searchQueryId' => Type::nonNull(Type::id()),
            ],
        ]);
    }

    public static function searchSuggestionProduct(): ObjectType
    {
        return new ObjectType([
            'name' => 'SearchSuggestionProduct',
            'fields' => [
                'productId' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'thumbnailUrl' => Type::string(),
            ],
        ]);
    }

    public static function searchSuggestionEntry(): ObjectType
    {
        return new ObjectType([
            'name' => 'SearchSuggestionEntry',
            'fields' => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
            ],
        ]);
    }

    public static function searchSuggestions(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'SearchSuggestions',
            'fields' => [
                'products' => Type::listOf($types->get('SearchSuggestionProduct')),
                'collections' => Type::listOf($types->get('SearchSuggestionEntry')),
                'categories' => Type::listOf($types->get('SearchSuggestionEntry')),
                'popularQueries' => Type::listOf(Type::string()),
            ],
        ]);
    }
}
