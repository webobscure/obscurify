<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\Stores\Models\Store;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * The resolved tenant itself — deliberately minimal, no `settings`
 * blob (TD-38 in docs/architecture/technical-debt.md flags that field
 * as unfiltered even in the REST admin API; GraphQL's public surface
 * has even less reason to expose it).
 */
final class StoreType
{
    public static function make(): ObjectType
    {
        return new ObjectType([
            'name' => 'Store',
            'fields' => fn () => [
                'id' => Type::nonNull(Type::id()),
                'name' => Type::nonNull(Type::string()),
                'slug' => Type::nonNull(Type::string()),
                'defaultCurrency' => Type::nonNull(Type::string()),
                'defaultLocale' => Type::nonNull(Type::string()),
                'timezone' => Type::nonNull(Type::string()),
            ],
            'resolveField' => fn (Store $store, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'defaultCurrency' => $store->default_currency,
                'defaultLocale' => $store->default_locale,
                'timezone' => $store->timezone,
                default => null,
            },
        ]);
    }
}
