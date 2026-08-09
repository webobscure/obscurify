<?php

use App\Domain\Orders\Models\OrderItem;
use App\Domain\Shipping\Models\Shipment;
use App\Domain\Shipping\Models\ShipmentItem;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Completes a checkout, then drives it to financial_status=paid via a real
 * signed fake payment webhook — CreateFulfillment/CreateShipment both
 * require this, and building on the genuine payment flow (rather than
 * factory-setting the status directly) exercises the same path a real
 * order takes. $store is passed explicitly (rather than resolved from
 * $host) since this is a free function, not a closure bound to the test
 * case.
 *
 * @return array{order_id: string, order_item_id: string}
 */
function paidOrderFor(string $host, string $variantId, Store $store, int $quantity = 1): array
{
    ['order_id' => $orderId] = completedOrderFor($host, $variantId, $quantity);

    $payment = test()->postJson(
        storefrontUrl($host, "/api/v1/storefront/orders/{$orderId}/payments"),
        ['provider' => 'fake'],
        ['Idempotency-Key' => 'pay-'.Str::random(16)],
    )->assertCreated();

    $externalPaymentId = Str::after($payment->json('data.redirect_url'), '/fake-payments/');

    $payload = [
        'event_id' => (string) Str::ulid(),
        'external_payment_id' => $externalPaymentId,
        'event_type' => 'payment.updated',
        'status' => 'succeeded',
        'amount' => $payment->json('data.amount'),
        'currency' => $payment->json('data.currency'),
        'timestamp' => now()->timestamp,
    ];
    $raw = json_encode($payload);
    $signature = hash_hmac('sha256', $raw, (string) config('payments.fake.secret'));

    test()->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], [
        'HTTP_X-Fake-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $raw)->assertOk();

    $orderItemId = app(TenantContext::class)->scope(
        $store,
        fn () => OrderItem::query()->where('order_id', $orderId)->firstOrFail()->id,
    );

    return ['order_id' => $orderId, 'order_item_id' => $orderItemId];
}

/**
 * Drives a paid Order through the full Fulfillment lifecycle (allocate,
 * pick, pack — which auto-advances to `ready`) and creates the Shipment
 * against it — the only path a Shipment can be created through since
 * Milestone 7 (Fulfillment Core). Returns the /complete response;
 * data.shipments.0 is the Shipment this call created.
 *
 * @param  list<array{order_item_id: string, quantity: int}>  $lines
 */
function shipViaFulfillment(User $user, Store $store, string $orderId, array $lines, string $provider = 'fake'): TestResponse
{
    $t = test();

    $fulfillment = $t->actingAs($user, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => $lines,
    ], tenantHeader($store))->assertCreated();

    $fulfillmentId = $fulfillment->json('data.id');
    $items = $fulfillment->json('data.items');

    $t->actingAs($user, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/allocate", [], tenantHeader($store))->assertOk();

    $t->actingAs($user, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pick", [
        'items' => collect($items)->map(fn ($item) => ['fulfillment_item_id' => $item['id'], 'picked_quantity' => $item['quantity']])->values()->all(),
    ], tenantHeader($store))->assertOk();

    $t->actingAs($user, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pack", [
        'items' => collect($items)->map(fn ($item) => ['fulfillment_item_id' => $item['id'], 'packed_quantity' => $item['quantity']])->values()->all(),
    ], tenantHeader($store))->assertOk();

    return $t->actingAs($user, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => $provider,
    ], tenantHeader($store));
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
});

it('creates a shipment, assigns a tracking number, and records a created tracking event', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 3);

    $response = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 3]])
        ->assertOk();

    expect($response->json('data.shipments.0.status'))->toBe('created')
        ->and($response->json('data.shipments.0.tracking_number'))->toStartWith('FAKE')
        ->and($response->json('data.shipments.0.items.0.quantity'))->toBe(3)
        ->and($response->json('data.shipments.0.tracking_events.0.status'))->toBe('created')
        ->and($response->json('data.status'))->toBe('completed');

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        $shipment = Shipment::query()->where('order_id', $orderId)->firstOrFail();
        expect($shipment->external_shipment_id)->toStartWith('fake_ship_')
            ->and($shipment->tracking_url)->toContain($shipment->external_shipment_id)
            ->and($shipment->fulfillment_id)->not->toBeNull();
    });
});

it('supports partial shipment: quantity may be less than the OrderItem quantity, across multiple shipments', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 5);

    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 3]])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        expect(Shipment::query()->where('order_id', $orderId)->count())->toBe(2)
            ->and((int) ShipmentItem::query()->sum('quantity'))->toBe(5);
    });
});

it('rejects a fulfillment that would fulfill more than was ordered', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 2);

    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'fulfillment_overshipment');
});

it('rejects creating a fulfillment before the order is paid', function () {
    ['order_id' => $orderId] = completedOrderFor('store-a.localhost', $this->variantA->id);

    $orderItemId = app(TenantContext::class)->scope($this->storeA, fn () => OrderItem::query()->where('order_id', $orderId)->firstOrFail()->id);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(422);
});

it('never lets Store B create a fulfillment against a Store A order', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);

    $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ], tenantHeader($this->storeB))->assertNotFound();
});

it('never lets a Store A fulfillment reference a Store B OrderItem', function () {
    [$this->productB, $this->variantB] = productWithStock($this->storeB, 10);

    ['order_id' => $orderIdA] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    ['order_item_id' => $orderItemIdB] = paidOrderFor('store-b.localhost', $this->variantB->id, $this->storeB);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderIdA}/fulfillments", [
        'items' => [['order_item_id' => $orderItemIdB, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(422);
});

it('cancels a shipment, recording a cancelled tracking event', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);

    $created = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]])->assertOk();

    $shipmentId = $created->json('data.shipments.0.id');

    $cancelled = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/cancel", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($cancelled->json('data.status'))->toBe('cancelled')
        ->and(collect($cancelled->json('data.tracking_events'))->pluck('status'))->toContain('cancelled');
});

it('rejects cancelling an already-delivered shipment', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);

    $created = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]])->assertOk();

    $shipmentId = $created->json('data.shipments.0.id');

    app(TenantContext::class)->scope($this->storeA, function () use ($shipmentId) {
        Shipment::query()->whereKey($shipmentId)->update(['status' => 'delivered']);
    });

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/cancel", [], tenantHeader($this->storeA))
        ->assertStatus(409)->assertJsonPath('error', 'invalid_shipment_transition');
});
