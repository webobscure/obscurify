<?php

namespace App\Domain\GraphQL\Queries;

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Collections\Enums\CollectionStatus;
use App\Domain\Collections\Models\Collection as CollectionModel;
use App\Domain\GraphQL\Exceptions\GraphQLUserError;
use App\Domain\GraphQL\Support\GraphQLContext;
use App\Domain\GraphQL\Support\QueryCache;
use App\Domain\GraphQL\Support\QueryFieldRegistry;
use App\Domain\GraphQL\Support\TypeRegistry;
use App\Domain\GraphQL\Types\CommonTypes;
use GraphQL\Type\Definition\Type;

/**
 * `store`/`products`/`product`/`collections`/`collection`/`categories`/
 * `category` (spec section 3) — every query here runs the exact same
 * Eloquent query StorefrontProductController/StorefrontCollectionController/
 * StorefrontCategoryController already run (see those classes' own
 * docblocks for the tenant-isolation guarantee: `Product`/`Collection`/
 * `Category` all use BelongsToTenant, so this can never cross a store
 * boundary regardless of caller). No dedicated Application service
 * exists for these storefront reads in REST either — see
 * docs/architecture/graphql.md §2 for why replicating a controller's
 * query here is not "querying Eloquent directly" in the sense spec
 * section 2 forbids (that rule targets bypassing write-side business
 * logic, not read-only listings that were never behind a service to
 * begin with).
 */
final class CatalogQueries
{
    public static function register(QueryFieldRegistry $queries, TypeRegistry $types): void
    {
        $queries->register('store', [
            'type' => $types->get('Store'),
            'resolve' => fn (mixed $root, array $args, GraphQLContext $context) => $context->store,
        ]);

        $queries->register('products', [
            'type' => $types->get('ProductConnection'),
            'args' => [
                'collection' => Type::string(),
                'category' => Type::string(),
                'sort' => Type::string(),
                'page' => Type::int(),
                'perPage' => Type::int(),
            ],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $sort = $args['sort'] ?? 'newest';

                $products = Product::query()
                    ->where('store_id', $context->store->id)
                    ->where('status', ProductStatus::Active)
                    ->when($args['collection'] ?? null, fn ($q, $slug) => $q->whereHas('collections', fn ($qq) => $qq->where('slug', $slug)))
                    ->when($args['category'] ?? null, fn ($q, $slug) => $q->whereHas('categories', fn ($qq) => $qq->where('slug', $slug)))
                    ->withMin(['variants as min_variant_price' => fn ($q) => $q->where('status', ProductStatus::Active)], 'price_amount')
                    ->with(self::eagerLoads())
                    ->when($sort === 'price_asc', fn ($q) => $q->orderBy('min_variant_price'))
                    ->when($sort === 'price_desc', fn ($q) => $q->orderByDesc('min_variant_price'))
                    ->when(! in_array($sort, ['price_asc', 'price_desc'], true), fn ($q) => $q->orderByDesc('created_at'))
                    ->paginate($args['perPage'] ?? 15, ['*'], 'page', $args['page'] ?? 1);

                return ['data' => $products->items(), 'pageInfo' => CommonTypes::resolvePageInfo($products)];
            },
        ]);

        $queries->register('product', [
            'type' => $types->get('Product'),
            'args' => ['slug' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $product = Product::query()
                    ->where('store_id', $context->store->id)
                    ->where('slug', $args['slug'])
                    ->where('status', ProductStatus::Active)
                    ->withMin(['variants as min_variant_price' => fn ($q) => $q->where('status', ProductStatus::Active)], 'price_amount')
                    ->with(self::eagerLoads())
                    ->first();

                return $product ?? throw GraphQLUserError::notFound('Product');
            },
        ]);

        $queries->register('collections', [
            'type' => $types->get('CollectionConnection'),
            'args' => ['page' => Type::int(), 'perPage' => Type::int()],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $collections = CollectionModel::query()
                    ->where('store_id', $context->store->id)
                    ->where('status', CollectionStatus::Active)
                    ->orderBy('title')
                    ->paginate($args['perPage'] ?? 15, ['*'], 'page', $args['page'] ?? 1);

                return ['data' => $collections->items(), 'pageInfo' => CommonTypes::resolvePageInfo($collections)];
            },
        ]);

        $queries->register('collection', [
            'type' => $types->get('Collection'),
            'args' => ['slug' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $collection = CollectionModel::query()
                    ->where('store_id', $context->store->id)
                    ->where('slug', $args['slug'])
                    ->where('status', CollectionStatus::Active)
                    ->first();

                return $collection ?? throw GraphQLUserError::notFound('Collection');
            },
        ]);

        $queries->register('categories', [
            'type' => Type::listOf($types->get('Category')),
            'resolve' => fn (mixed $root, array $args, GraphQLContext $context) => QueryCache::remember(
                $context->store->id,
                'categories',
                fn () => Category::query()
                    ->where('store_id', $context->store->id)
                    ->whereNull('parent_id')
                    ->with('children')
                    ->orderBy('position')
                    ->get()
                    ->all(),
            ),
        ]);

        $queries->register('category', [
            'type' => $types->get('Category'),
            'args' => ['slug' => Type::nonNull(Type::string())],
            'resolve' => function (mixed $root, array $args, GraphQLContext $context) {
                $category = Category::query()
                    ->where('store_id', $context->store->id)
                    ->where('slug', $args['slug'])
                    ->with('children')
                    ->first();

                return $category ?? throw GraphQLUserError::notFound('Category');
            },
        ]);
    }

    /**
     * @return array<int|string, \Closure|string>
     */
    private static function eagerLoads(): array
    {
        return [
            'variants' => fn ($q) => $q->where('status', ProductStatus::Active),
            'variants.optionValues.option',
            'variants.inventoryItem.levels',
            'media',
        ];
    }
}
