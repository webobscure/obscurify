<?php

namespace App\Domain\GraphQL\Types;

use App\Domain\RussianCommerce\Models\PaymentMethodSettings;
use App\Domain\RussianCommerce\Models\StoreLegalProfile;
use App\Domain\Stores\Models\Store;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;

/**
 * The resolved tenant itself — deliberately minimal, no `settings`
 * blob (TD-38 in docs/architecture/technical-debt.md flags that field
 * as unfiltered even in the REST admin API; GraphQL's public surface
 * has even less reason to expose it). `seller`/`paymentMethods` mirror
 * StorefrontStoreResource exactly (Russian Commerce Foundation, spec
 * section 18) — same minimal-exposure rule applies here.
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
                'seller' => self::seller(),
                'paymentMethods' => Type::nonNull(Type::listOf(Type::nonNull(Type::string()))),
            ],
            'resolveField' => fn (Store $store, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'id' => $store->id,
                'name' => $store->name,
                'slug' => $store->slug,
                'defaultCurrency' => $store->default_currency,
                'defaultLocale' => $store->default_locale,
                'timezone' => $store->timezone,
                'seller' => self::resolveSeller($store),
                'paymentMethods' => self::resolvePaymentMethods($store),
                default => null,
            },
        ]);
    }

    private static function seller(): ObjectType
    {
        return new ObjectType([
            'name' => 'Seller',
            'fields' => [
                'legalName' => Type::nonNull(Type::string()),
                'inn' => Type::nonNull(Type::string()),
            ],
            'resolveField' => fn (array $seller, array $args, mixed $context, ResolveInfo $info) => match ($info->fieldName) {
                'legalName' => $seller['legal_name'],
                'inn' => $seller['inn'],
                default => null,
            },
        ]);
    }

    /**
     * @return array{legal_name: string, inn: string}|null
     */
    private static function resolveSeller(Store $store): ?array
    {
        $profile = StoreLegalProfile::query()->where('store_id', $store->id)->first();

        if ($profile === null) {
            return null;
        }

        return ['legal_name' => $profile->legal_name, 'inn' => $profile->inn];
    }

    /**
     * @return list<string>
     */
    private static function resolvePaymentMethods(Store $store): array
    {
        $settings = PaymentMethodSettings::query()->where('store_id', $store->id)->first();

        return $settings !== null ? $settings->enabled_methods : [];
    }
}
