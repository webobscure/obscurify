<?php

use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Models\Order;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * A GraphQL-suite-local copy of tests/Feature/Cart/CartTest.php's own
 * `productWithStock` — same fixture shape, distinctly named so both
 * files can coexist in one full suite run without a duplicate top-level
 * function declaration.
 *
 * @return array{0: Product, 1: ProductVariant}
 */
function graphqlProductWithStock(Store $store, int $onHand): array
{
    return app(TenantContext::class)->scope($store, function () use ($onHand) {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'status' => ProductStatus::Active, 'price_amount' => 1000]);
        $item = InventoryItem::factory()->create(['product_variant_id' => $variant->id, 'tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => $onHand, 'reserved' => 0]);

        return [$product, $variant];
    });
}

beforeEach(function () {
    $this->withCredentials();

    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-graphql-cart.localhost';
    domainForStore($this->store, $this->host);
    [$this->product, $this->variant] = graphqlProductWithStock($this->store, 10);
});

it('adds, updates, and removes a cart item, returning the cart via cookie-based identity', function () {
    $add = graphqlRequest($this->host, '
        mutation($variantId: ID!) {
          addCartItem(variantId: $variantId, quantity: 2) {
            token
            itemCount
            items { quantity variant { id } }
          }
        }
    ', ['variantId' => $this->variant->id]);

    $add->assertOk();
    expect($add->json('data.addCartItem.itemCount'))->toBe(2);
    $itemId = null;

    $cartCookie = collect($add->headers->getCookies())->first(fn ($c) => $c->getName() === 'storefront_cart_token');
    expect($cartCookie)->not->toBeNull();

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $cartRead = graphqlRequest($this->host, 'query { cart { items { id quantity } } }');
    $itemId = $cartRead->json('data.cart.items.0.id');
    expect($itemId)->not->toBeNull();

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $update = graphqlRequest($this->host, '
        mutation($itemId: ID!) { updateCartItem(itemId: $itemId, quantity: 5) { itemCount } }
    ', ['itemId' => $itemId]);
    expect($update->json('data.updateCartItem.itemCount'))->toBe(5);

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $remove = graphqlRequest($this->host, '
        mutation($itemId: ID!) { removeCartItem(itemId: $itemId) { itemCount } }
    ', ['itemId' => $itemId]);
    expect($remove->json('data.removeCartItem.itemCount'))->toBe(0);
});

it('never lets one guest cart mutate another guest cart\'s items', function () {
    $addA = graphqlRequest($this->host, '
        mutation($variantId: ID!) { addCartItem(variantId: $variantId, quantity: 1) { items { id } } }
    ', ['variantId' => $this->variant->id]);
    $itemIdA = $addA->json('data.addCartItem.items.0.id');

    // A second, independent guest cart (no cookie carried over).
    $updateFromB = graphqlRequest($this->host, '
        mutation($itemId: ID!) { updateCartItem(itemId: $itemId, quantity: 9) { itemCount } }
    ', ['itemId' => $itemIdA]);

    expect($updateFromB->json('data.updateCartItem'))->toBeNull();
    expect($updateFromB->json('errors.0.message'))->toBe('Cart item not found.');
});

it('opens and updates a checkout, then completes it into a real Order with the Idempotency-Key header', function () {
    $add = graphqlRequest($this->host, '
        mutation($variantId: ID!) { addCartItem(variantId: $variantId, quantity: 1) { id } }
    ', ['variantId' => $this->variant->id]);
    $cartCookie = collect($add->headers->getCookies())->first(fn ($c) => $c->getName() === 'storefront_cart_token');

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $open = graphqlRequest($this->host, 'mutation { openCheckout { id status total { amount } } }');
    $open->assertOk();
    expect($open->json('data.openCheckout.status'))->toBe('open');

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $update = graphqlRequest($this->host, '
        mutation {
          updateCheckout(
            email: "buyer@example.test"
            shippingAddress: { firstName: "Ada", lastName: "Lovelace", countryCode: "US", region: "CA", city: "San Francisco", postalCode: "94107", addressLine1: "123 Analytical Engine Way" }
          ) { email shippingAddress { firstName city } }
        }
    ');
    $update->assertOk();
    expect($update->json('data.updateCheckout.email'))->toBe('buyer@example.test');
    expect($update->json('data.updateCheckout.shippingAddress.city'))->toBe('San Francisco');

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $withoutKey = $this->postJson(storefrontUrl($this->host, '/api/graphql'), ['query' => 'mutation { completeCheckout { id } }']);
    expect($withoutKey->json('errors.0.message'))->toBe('The Idempotency-Key header is required.');

    $this->withUnencryptedCookie('storefront_cart_token', $cartCookie->getValue());
    $complete = $this->postJson(
        storefrontUrl($this->host, '/api/graphql'),
        ['query' => 'mutation { completeCheckout { id orderStatus financialStatus } }'],
        ['Idempotency-Key' => 'test-key-1'],
    );

    $complete->assertOk();
    expect($complete->json('data.completeCheckout.id'))->not->toBeNull();

    $orderId = $complete->json('data.completeCheckout.id');
    app(TenantContext::class)->scope($this->store, function () use ($orderId) {
        expect(Order::query()->where('id', $orderId)->exists())->toBeTrue();
    });
});
