<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Collections\Models\Collection as CollectionModel;
use App\Domain\GraphQL\DataLoaders\CategoryLoader;
use App\Domain\GraphQL\DataLoaders\CollectionLoader;
use App\Domain\GraphQL\Support\TypeRegistry;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use Illuminate\Support\Facades\Storage;

/**
 * Catalog-facing object types (Product/ProductVariant/Collection/
 * Category) — every read here mirrors StorefrontProductResource/
 * StorefrontProductVariantResource/StorefrontMediaResource exactly
 * (see docs/architecture/graphql.md §2: "never query Eloquent models
 * directly from GraphQL" means never *introduce new query logic*, not
 * that resolvers can't read the same already-loaded relation a REST
 * Resource would).
 */
final class CatalogTypes
{
    public static function media(): ObjectType
    {
        return CommonTypes::media();
    }

    public static function productVariant(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'ProductVariant',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'sku' => Type::string(),
                'price' => $types->get('Money'),
                'compareAtPrice' => $types->get('Money'),
                'options' => Type::listOf($types->get('ProductOptionEntry')),
                'availability' => $types->get('Availability'),
                'media' => Type::listOf($types->get('Media')),
            ],
            'resolveField' => function (ProductVariant $variant, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id', 'title', 'sku' => $variant->{$info->fieldName},
                    'price' => ['amount' => $variant->price_amount, 'currency' => $variant->currency],
                    'compareAtPrice' => $variant->compare_at_price_amount === null ? null : ['amount' => $variant->compare_at_price_amount, 'currency' => $variant->currency],
                    'options' => $variant->relationLoaded('optionValues')
                        ? $variant->optionValues->map(fn ($value) => ['option' => $value->option->name, 'value' => $value->value])->values()->all()
                        : [],
                    'availability' => CommonTypes::resolveAvailability($variant),
                    'media' => [],
                    default => null,
                };
            },
        ]);
    }

    public static function product(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Product',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'description' => Type::string(),
                'vendor' => Type::string(),
                'productType' => Type::string(),
                'seo' => $types->get('Seo'),
                'price' => $types->get('Money'),
                'variants' => Type::listOf($types->get('ProductVariant')),
                'media' => Type::listOf($types->get('Media')),
                'collections' => Type::listOf($types->get('Collection')),
                'categories' => Type::listOf($types->get('Category')),
            ],
            'resolveField' => function (Product $product, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id', 'title', 'slug', 'description', 'vendor' => $product->{$info->fieldName},
                    'productType' => $product->product_type,
                    'seo' => ['title' => $product->seo_title, 'description' => $product->seo_description],
                    'price' => self::resolveMinPrice($product),
                    'variants' => $product->relationLoaded('variants') ? $product->variants->all() : [],
                    'media' => $product->relationLoaded('media') ? $product->media->sortBy('position')->map(fn ($m) => self::mediaArray($m))->values()->all() : [],
                    // Deliberately DataLoader-backed, not eager-loaded by
                    // the parent `products`/`product` query resolver —
                    // see CollectionLoader/CategoryLoader's own docblocks
                    // for why this field specifically is the genuine
                    // GraphQL-level N+1 risk case.
                    'collections' => app(CollectionLoader::class)->loadForProduct($product->id),
                    'categories' => app(CategoryLoader::class)->loadForProduct($product->id),
                    default => null,
                };
            },
        ]);
    }

    public static function productConnection(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'ProductConnection',
            'fields' => [
                'data' => Type::listOf($types->get('Product')),
                'pageInfo' => $types->get('PageInfo'),
            ],
        ]);
    }

    public static function collection(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Collection',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'description' => Type::string(),
            ],
            'resolveField' => fn (CollectionModel $collection, array $args, mixed $context, ResolveInfo $info) => $collection->{$info->fieldName},
        ]);
    }

    public static function collectionConnection(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'CollectionConnection',
            'fields' => [
                'data' => Type::listOf($types->get('Collection')),
                'pageInfo' => $types->get('PageInfo'),
            ],
        ]);
    }

    public static function category(TypeRegistry $types): ObjectType
    {
        return new ObjectType([
            'name' => 'Category',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'title' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'position' => Type::nonNull(Type::int()),
                'children' => Type::listOf($types->get('Category')),
            ],
            'resolveField' => function (Category $category, array $args, mixed $context, ResolveInfo $info) {
                return match ($info->fieldName) {
                    'id', 'title', 'slug', 'position' => $category->{$info->fieldName},
                    'children' => $category->relationLoaded('children') ? $category->children->all() : [],
                    default => null,
                };
            },
        ]);
    }

    /**
     * @return array{amount: int, currency: string}|null
     */
    public static function resolveMinPrice(Product $product): ?array
    {
        $minPrice = $product->getAttribute('min_variant_price');

        if ($minPrice === null) {
            return null;
        }

        return ['amount' => (int) $minPrice, 'currency' => $product->store->default_currency];
    }

    /**
     * @return array{url: string, alt: string|null, position: int}
     */
    public static function mediaArray(mixed $media): array
    {
        return [
            'url' => Storage::disk($media->disk)->url($media->path),
            'alt' => $media->alt,
            'position' => $media->position,
        ];
    }
}
