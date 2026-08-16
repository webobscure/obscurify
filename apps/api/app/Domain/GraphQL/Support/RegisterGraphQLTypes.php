<?php

namespace App\Domain\GraphQL\Support;

use App\Domain\GraphQL\Scalars\DateTimeScalar;
use App\Domain\GraphQL\Scalars\JSONScalar;
use App\Domain\GraphQL\Types\AnalyticsTypes;
use App\Domain\GraphQL\Types\CartTypes;
use App\Domain\GraphQL\Types\CatalogTypes;
use App\Domain\GraphQL\Types\CheckoutTypes;
use App\Domain\GraphQL\Types\CmsTypes;
use App\Domain\GraphQL\Types\CommonTypes;
use App\Domain\GraphQL\Types\CustomerTypes;
use App\Domain\GraphQL\Types\NotificationTypes;
use App\Domain\GraphQL\Types\OrderTypes;
use App\Domain\GraphQL\Types\SearchTypes;
use App\Domain\GraphQL\Types\StoreType;

/**
 * Every built-in named type, registered once at boot
 * (GraphQLServiceProvider::boot()) before the schema is ever built.
 * Registration order doesn't matter — every type is a lazy factory
 * (see TypeRegistry), so a type referencing another one not yet
 * registered is fine as long as it *is* registered by the time the
 * schema is actually executed against.
 */
final class RegisterGraphQLTypes
{
    public function handle(TypeRegistry $types): void
    {
        $types->register('DateTime', fn () => new DateTimeScalar);
        $types->register('JSON', fn () => new JSONScalar);

        $types->register('Money', fn () => CommonTypes::money());
        $types->register('PriceRange', fn () => CommonTypes::priceRange());
        $types->register('Media', fn () => CommonTypes::media());
        $types->register('Seo', fn () => CommonTypes::seo());
        $types->register('PageInfo', fn () => CommonTypes::pageInfo());
        $types->register('Availability', fn () => CommonTypes::availability());
        $types->register('ProductOptionEntry', fn () => CommonTypes::productOptionEntry());

        $types->register('Store', fn () => StoreType::make());

        $types->register('ProductVariant', fn () => CatalogTypes::productVariant($types));
        $types->register('Product', fn () => CatalogTypes::product($types));
        $types->register('ProductConnection', fn () => CatalogTypes::productConnection($types));
        $types->register('Collection', fn () => CatalogTypes::collection($types));
        $types->register('CollectionConnection', fn () => CatalogTypes::collectionConnection($types));
        $types->register('Category', fn () => CatalogTypes::category($types));

        $types->register('Page', fn () => CmsTypes::page($types));
        $types->register('MenuItem', fn () => CmsTypes::menuItem($types));
        $types->register('Menu', fn () => CmsTypes::menu($types));

        $types->register('SearchResultItem', fn () => SearchTypes::searchResultItem($types));
        $types->register('SearchResult', fn () => SearchTypes::searchResult($types));
        $types->register('SearchSuggestionProduct', fn () => SearchTypes::searchSuggestionProduct());
        $types->register('SearchSuggestionEntry', fn () => SearchTypes::searchSuggestionEntry());
        $types->register('SearchSuggestions', fn () => SearchTypes::searchSuggestions($types));

        $types->register('CartItem', fn () => CartTypes::cartItem($types));
        $types->register('Cart', fn () => CartTypes::cart($types));
        $types->register('Checkout', fn () => CheckoutTypes::checkout($types));

        $types->register('CustomerAddress', fn () => CustomerTypes::customerAddress());
        $types->register('Customer', fn () => CustomerTypes::customer($types));
        $types->register('AuthPayload', fn () => CustomerTypes::authPayload($types));
        $types->register('TokenPair', fn () => CustomerTypes::tokenPair());
        $types->register('AddressInput', fn () => CustomerTypes::addressInput());

        $types->register('AddressSnapshot', fn () => OrderTypes::orderAddress());
        $types->register('OrderItem', fn () => OrderTypes::orderItem($types));
        $types->register('Order', fn () => OrderTypes::order($types));
        $types->register('OrderConnection', fn () => OrderTypes::orderConnection($types));

        $types->register('Notification', fn () => NotificationTypes::notification());
        $types->register('NotificationRecipient', fn () => NotificationTypes::notificationRecipient($types));
        $types->register('NotificationConnection', fn () => NotificationTypes::notificationConnection($types));
        $types->register('NotificationPreference', fn () => NotificationTypes::notificationPreference());

        $types->register('AnalyticsReport', fn () => AnalyticsTypes::report($types));
    }
}
