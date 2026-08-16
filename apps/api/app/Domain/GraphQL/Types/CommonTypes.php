<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Inventory\Support\VariantAvailability;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

/**
 * Small, widely-reused object types shared across the schema — grouped
 * into one file (unlike the one-domain-per-file `*Types` classes below)
 * because each is a handful of scalar fields with no domain of its own,
 * the GraphQL analogue of the REST layer's repeated `{amount, currency}`/
 * `{url, alt, position}` inline shapes.
 */
final class CommonTypes
{
    public static function money(): ObjectType
    {
        return new ObjectType([
            'name' => 'Money',
            'fields' => [
                'amount' => Type::nonNull(Type::int()),
                'currency' => Type::nonNull(Type::string()),
            ],
        ]);
    }

    public static function priceRange(): ObjectType
    {
        return new ObjectType([
            'name' => 'PriceRange',
            'fields' => [
                'min' => Type::int(),
                'max' => Type::int(),
                'currency' => Type::string(),
            ],
        ]);
    }

    public static function media(): ObjectType
    {
        return new ObjectType([
            'name' => 'Media',
            'fields' => [
                'url' => Type::nonNull(Type::string()),
                'alt' => Type::string(),
                'position' => Type::nonNull(Type::int()),
            ],
        ]);
    }

    public static function seo(): ObjectType
    {
        return new ObjectType([
            'name' => 'Seo',
            'fields' => [
                'title' => Type::string(),
                'description' => Type::string(),
            ],
        ]);
    }

    public static function pageInfo(): ObjectType
    {
        return new ObjectType([
            'name' => 'PageInfo',
            'fields' => [
                'currentPage' => Type::nonNull(Type::int()),
                'lastPage' => Type::nonNull(Type::int()),
                'perPage' => Type::nonNull(Type::int()),
                'total' => Type::nonNull(Type::int()),
            ],
        ]);
    }

    public static function availability(): ObjectType
    {
        return new ObjectType([
            'name' => 'Availability',
            'fields' => [
                'tracked' => Type::nonNull(Type::boolean()),
                // Nullable, not a bug: VariantAvailability::for() returns
                // `available: null` for an untracked variant (untracked
                // means unlimited, not zero) — matching REST's own
                // `StorefrontAvailability.available: number | null`.
                'available' => Type::int(),
                'inStock' => Type::nonNull(Type::boolean()),
            ],
        ]);
    }

    public static function productOptionEntry(): ObjectType
    {
        return new ObjectType([
            'name' => 'ProductOptionEntry',
            'fields' => [
                'option' => Type::nonNull(Type::string()),
                'value' => Type::nonNull(Type::string()),
            ],
        ]);
    }

    /**
     * @return array{tracked: bool, available: int, inStock: bool}
     */
    public static function resolveAvailability(mixed $variant): array
    {
        $availability = VariantAvailability::for($variant);

        return [
            'tracked' => $availability->tracked,
            'available' => $availability->available,
            'inStock' => $availability->inStock,
        ];
    }

    /**
     * @return array{currentPage: int, lastPage: int, perPage: int, total: int}
     */
    public static function resolvePageInfo(mixed $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
