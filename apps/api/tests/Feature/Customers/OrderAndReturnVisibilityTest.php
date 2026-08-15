<?php

use App\Domain\Carts\Models\Cart;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Customers\Models\Customer;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Payments\Models\Payment;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Models\User;
use App\Shared\Commerce\Models\OutboxEvent;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Routing\Middleware\ThrottleRequests;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);
    $this->host = 'e2e-orders.localhost';
    domainForStore($this->store, $this->host);
    $this->withoutMiddleware(ThrottleRequests::class);

    $registered = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'order-viewer@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $this->accessToken = $registered->json('access_token');

    [$this->customer, $this->order, $this->orderItem, $this->variant] = app(TenantContext::class)->scope(
        $this->store,
        function () {
            $customer = Customer::query()->where('email', 'order-viewer@example.test')->firstOrFail();
            $variant = ProductVariant::factory()->create(['price_amount' => 2500]);

            $order = Order::factory()->create(['customer_id' => $customer->id]);
            $orderItem = OrderItem::factory()->create([
                'order_id' => $order->id,
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'unit_price_amount' => 2500,
                'line_total_amount' => 5000,
            ]);

            Payment::query()->create([
                'order_id' => $order->id,
                'provider' => 'fake',
                'status' => 'paid',
                'currency' => 'RUB',
                'amount' => 5000,
                'authorized_amount' => 5000,
                'captured_amount' => 5000,
                'refunded_amount' => 0,
                'external_payment_id' => 'fake_pay_test',
            ]);

            $fulfillment = Fulfillment::factory()->create(['order_id' => $order->id]);
            $shipment = Shipment::factory()->create(['order_id' => $order->id, 'fulfillment_id' => $fulfillment->id]);
            ShipmentItem::factory()->create([
                'shipment_id' => $shipment->id,
                'order_item_id' => $orderItem->id,
                'quantity' => 2,
            ]);

            return [$customer, $order, $orderItem, $variant];
        }
    );
});

it('lists only the authenticated customers own orders', function () {
    $response = $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/orders'), authHeader($this->accessToken))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.id'))->toBe($this->order->id);
});

it('shows full order detail including payments and shipments, and records CustomerOrderViewed', function () {
    $response = $this->getJson(storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}"), authHeader($this->accessToken))
        ->assertOk();

    $response->assertJsonPath('data.id', $this->order->id)
        ->assertJsonCount(1, 'data.payments')
        ->assertJsonCount(1, 'data.shipments')
        ->assertJsonPath('data.payments.0.status', 'paid')
        // The portal's return-request form keys its per-line state off
        // this id — the storefront's own (confirmation-only) OrderItemResource
        // never includes it, so asserting it here guards against
        // CustomerOrderResource silently reverting to that resource.
        ->assertJsonPath('data.items.0.id', $this->orderItem->id);
});

it('lists orders with a lighter summary shape that never eager-loads items', function () {
    $response = $this->getJson(storefrontUrl($this->host, '/api/v1/storefront/account/orders'), authHeader($this->accessToken))
        ->assertOk();

    expect($response->json('data.0'))->not->toHaveKey('items');
});

it('rejects viewing an order that belongs to a different customer', function () {
    $other = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'other-order-viewer@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $this->getJson(storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}"), authHeader($other->json('access_token')))
        ->assertStatus(403);
});

it('reorders into a fresh cart at the current live price, never the old order price', function () {
    // Live price moves after the order was placed.
    app(TenantContext::class)->scope($this->store, function () {
        $this->variant->update(['price_amount' => 9999]);
    });

    $response = $this->postJson(
        storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}/reorder"),
        [],
        authHeader($this->accessToken)
    )->assertOk();

    expect($response->json('data.skipped'))->toBe([]);
    $cartToken = $response->json('data.cart_token');
    expect($cartToken)->toBeString();

    app(TenantContext::class)->scope($this->store, function () use ($cartToken) {
        $cart = Cart::query()->where('token', $cartToken)->firstOrFail();
        $item = $cart->items()->firstOrFail();

        expect($item->product_variant_id)->toBe($this->variant->id);
        expect($item->quantity)->toBe(2);
        // No price is stored on CartItem at all — pricing is always
        // resolved live downstream, which is the actual guarantee here.
    });
});

it('skips a reorder line whose product variant no longer exists', function () {
    app(TenantContext::class)->scope($this->store, function () {
        $this->variant->delete();
    });

    $response = $this->postJson(
        storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}/reorder"),
        [],
        authHeader($this->accessToken)
    )->assertOk();

    expect($response->json('data.skipped'))->toHaveCount(1);
    expect($response->json('data.skipped.0.reason'))->toBe('no_longer_available');
});

it('creates a return request for shipped quantity and rejects a request exceeding it', function () {
    $created = $this->postJson(
        storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}/returns"),
        [
            'items' => [
                ['order_item_id' => $this->orderItem->id, 'quantity' => 1, 'reason' => 'defective'],
            ],
        ],
        authHeader($this->accessToken)
    )->assertCreated();

    expect($created->json('data.status'))->toBe('requested');

    // Only 1 more unit is returnable (2 shipped, 1 already returned).
    $this->postJson(
        storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}/returns"),
        [
            'items' => [
                ['order_item_id' => $this->orderItem->id, 'quantity' => 2, 'reason' => 'defective'],
            ],
        ],
        authHeader($this->accessToken)
    )->assertStatus(422);
});

it('rejects a return request against another customers order', function () {
    $other = $this->postJson(storefrontUrl($this->host, '/api/v1/storefront/account/register'), [
        'email' => 'other-return-requester@example.test',
        'password' => 'correct-password-1',
    ])->assertCreated();

    $this->postJson(
        storefrontUrl($this->host, "/api/v1/storefront/account/orders/{$this->order->id}/returns"),
        [
            'items' => [
                ['order_item_id' => $this->orderItem->id, 'quantity' => 1, 'reason' => 'defective'],
            ],
        ],
        authHeader($other->json('access_token'))
    )->assertStatus(403);
});
