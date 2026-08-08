<?php

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Checkouts\Models\CheckoutAddress;
use App\Domain\Customers\Models\Customer;
use App\Domain\Customers\Models\CustomerAddress;
use App\Domain\Orders\Models\Order;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
});

it('keeps OrderItem/OrderAddress/Order snapshots unchanged after the live Product, Variant, and Customer are mutated', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 2,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'), [
        'email' => 'original-buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'country_code' => 'US',
            'city' => 'San Francisco',
            'address_line1' => 'Original Street 1',
        ],
    ])->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'immutability-key'],
    )->assertCreated();

    $orderId = $complete->json('data.id');

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        // Mutate every live record the snapshot could theoretically have
        // been derived from after the fact.
        Product::query()->whereKey($this->productA->id)->update(['title' => 'Renamed Product']);
        ProductVariant::query()->whereKey($this->variantA->id)->update([
            'title' => 'Renamed Variant',
            'sku' => 'RENAMED-SKU',
            'price_amount' => 999999,
        ]);

        $order = Order::query()->whereKey($orderId)->firstOrFail();
        Customer::query()->whereKey($order->customer_id)->update(['email' => 'mutated@example.com', 'first_name' => 'Mutated']);

        CheckoutAddress::query()->where('checkout_id', $order->checkout_id)->update([
            'first_name' => 'Mutated', 'city' => 'Mutated City', 'address_line1' => 'Mutated Street',
        ]);
    });

    $reloaded = $this->getJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}"))->assertOk();

    expect($reloaded->json('data.email'))->toBe('original-buyer@example.com')
        ->and($reloaded->json('data.items.0.product_title'))->toBe($this->productA->title)
        ->and($reloaded->json('data.items.0.variant_title'))->toBe($this->variantA->title)
        ->and($reloaded->json('data.items.0.sku'))->toBe($this->variantA->sku)
        ->and($reloaded->json('data.items.0.unit_price_amount'))->toBe($this->variantA->price_amount)
        ->and($reloaded->json('data.total_amount'))->toBe($this->variantA->price_amount * 2)
        ->and($reloaded->json('data.shipping_address.first_name'))->toBe('Ada')
        ->and($reloaded->json('data.shipping_address.city'))->toBe('San Francisco')
        ->and($reloaded->json('data.shipping_address.address_line1'))->toBe('Original Street 1');
});

it('never re-derives OrderAddress from CustomerAddress, so a customer address edit cannot touch a past order', function () {
    $add = $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/cart/items'), [
        'variant_id' => $this->variantA->id,
        'quantity' => 1,
    ])->assertOk();
    $token = $add->headers->getCookies()[0]->getValue();
    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->postJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'))->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $this->patchJson(storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout'), [
        'email' => 'repeat-buyer@example.com',
        'shipping_address' => [
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'country_code' => 'US', 'city' => 'San Francisco',
        ],
    ])->assertOk();

    $this->withUnencryptedCookie('storefront_cart_token', $token);
    $complete = $this->postJson(
        storefrontUrl('store-a.localhost', '/api/v1/storefront/checkout/complete'),
        [],
        ['Idempotency-Key' => 'customer-address-key'],
    )->assertCreated();

    $orderId = $complete->json('data.id');

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        $order = Order::query()->whereKey($orderId)->firstOrFail();

        // CompleteCheckout never writes a CustomerAddress row at all (see
        // its class docblock) — OrderAddress is always built from the
        // per-checkout CheckoutAddress snapshot instead. Creating one now
        // and pointing it at the same customer proves it has no bearing
        // on the already-placed order.
        CustomerAddress::query()->create([
            'customer_id' => $order->customer_id,
            'first_name' => 'Should Not Appear',
            'city' => 'Nowhere',
        ]);
    });

    $reloaded = $this->getJson(storefrontUrl('store-a.localhost', "/api/v1/storefront/orders/{$orderId}"))->assertOk();

    expect($reloaded->json('data.shipping_address.first_name'))->toBe('Ada')
        ->and($reloaded->json('data.shipping_address.city'))->toBe('San Francisco');
});
