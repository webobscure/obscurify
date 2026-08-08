<?php

use App\Domain\Carts\Models\Cart;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Locations\Models\Location;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Creates a Product + active Variant with tracked stock, via the same
 * TenantContext::scope() pattern used across the isolation test suites.
 *
 * @return array{0: Product, 1: ProductVariant}
 */
function productWithStock(Store $store, int $onHand): array
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

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
    [$this->productB, $this->variantB] = productWithStock($this->storeB, 10);
});

it('creates a guest cart for the resolved store and sets a cart cookie', function () {
    $response = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart'))
        ->assertOk()
        ->assertJsonPath('data.items', []);

    $cookie = $response->headers->getCookies()[0];
    expect($cookie->getName())->toBe('storefront_cart_token')
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getValue())->not->toBeEmpty();

    $cart = app(TenantContext::class)->scope($this->storeA, fn () => Cart::query()->where('token', $cookie->getValue())->first());
    expect($cart)->not->toBeNull()
        ->and($cart->store_id)->toBe($this->storeA->id);
});

it('restores the same cart by token across requests', function () {
    $first = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart'))->assertOk();
    $token = $first->headers->getCookies()[0]->getValue();

    $second = $this->withUnencryptedCookie('storefront_cart_token', $token)
        ->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart'));

    expect($second->json('data.id'))->toBe($first->json('data.id'));
});

it('adds an item, reports correct totals, updates quantity, and removes the item', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 2,
    ])->assertOk();

    expect($add->json('data.items.0.quantity'))->toBe(2)
        ->and($add->json('data.items_subtotal'))->toBe(2000)
        ->and($add->json('data.total'))->toBe(2000)
        ->and($add->json('data.currency'))->toBe('RUB');

    $token = $add->headers->getCookies()[0]->getValue();
    $itemId = $add->json('data.items.0.id');
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    $update = $this->patchJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/cart/items/{$itemId}"), ['quantity' => 5])->assertOk();
    expect($update->json('data.items.0.quantity'))->toBe(5)
        ->and($update->json('data.items_subtotal'))->toBe(5000);

    $this->deleteJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/cart/items/{$itemId}"))->assertNoContent();

    $after = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart'))->assertOk();
    expect($after->json('data.items'))->toBe([]);
});

it('accumulates quantity when the same variant is added twice', function () {
    $first = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 2,
    ])->assertOk();

    $token = $first->headers->getCookies()[0]->getValue();

    $second = $this->withUnencryptedCookie('storefront_cart_token', $token)
        ->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
            'variant_id' => $this->variantA->id,
            'quantity' => 3,
        ])->assertOk();

    expect($second->json('data.items'))->toHaveCount(1)
        ->and($second->json('data.items.0.quantity'))->toBe(5);
});

it('rejects adding a Store B variant while Store A is active', function () {
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantB->id,
        'quantity' => 1,
    ])->assertStatus(422);
});

it('rejects adding an inactive variant', function () {
    $inactive = app(TenantContext::class)->scope($this->storeA, function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => ProductStatus::Draft,
        ]);
    });

    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $inactive->id,
        'quantity' => 1,
    ])->assertStatus(422);
});

it('rejects a quantity that exceeds available stock', function () {
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 999,
    ])->assertStatus(422);
});

it('re-validates availability when updating quantity', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 2,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    $itemId = $add->json('data.items.0.id');
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    $this->patchJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/cart/items/{$itemId}"), ['quantity' => 999])
        ->assertStatus(422);
});

it('cannot access a Store A cart using its token while Store B is active', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    $itemId = $add->json('data.items.0.id');
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    // The token simply doesn't match any Store B cart (Cart::class is
    // tenant-scoped) — a brand new, empty Store B cart is created instead
    // of ever exposing Store A's.
    $onB = $this->getJson(storefrontUrl('store-b.localhost', '/api/v1/storefront/cart'))->assertOk();
    expect($onB->json('data.items'))->toBe([]);

    // And the Store A item id cannot be manipulated through that request either.
    $this->patchJson(storefrontUrl('store-b.localhost', "/api/v1/storefront/cart/items/{$itemId}"), ['quantity' => 1])
        ->assertNotFound();
});

it('does not let one guest cart modify another guest cart in the same store', function () {
    $cartOne = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $itemId = $cartOne->json('data.items.0.id');

    // A second, distinct guest cart (no cookie carried over) on the same store.
    $this->patchJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/cart/items/{$itemId}"), ['quantity' => 9])
        ->assertNotFound();
});

it('rejects a variant priced in a different currency than the cart', function () {
    $foreignVariant = app(TenantContext::class)->scope($this->storeA, function () {
        $product = Product::factory()->create(['status' => ProductStatus::Active]);

        return ProductVariant::factory()->create([
            'product_id' => $product->id,
            'status' => ProductStatus::Active,
            'currency' => 'USD',
        ]);
    });

    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $foreignVariant->id,
        'quantity' => 1,
    ])->assertStatus(422);
});

it('does not lose updates across many rapid additions of the same variant', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();

    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);

    foreach (range(1, 4) as $i) {
        $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
            'variant_id' => $this->variantA->id,
            'quantity' => 1,
        ])->assertOk();
    }

    $final = $this->getJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart'))->assertOk();
    expect($final->json('data.items.0.quantity'))->toBe(5)
        ->and($final->json('data.items'))->toHaveCount(1);
});
