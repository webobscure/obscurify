<?php

use App\Domain\Shipping\Infrastructure\Providers\FakeShippingProvider;
use App\Domain\Shipping\Models\Shipment;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Reference Provider Hardening (spec section 15): shipment-creation
 * failure/timeout simulation, threaded through CreateShipment ->
 * Shipment.metadata -> FakeShippingProvider — see that class's
 * maybeSimulateCreationFailure() docblock for why. Only reachable at all
 * when commerce.shipping.fake.failure_simulation.enabled.
 */
beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
});

function readyFulfillment(User $user, $store, string $orderId, array $lines): string
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

    return $fulfillmentId;
}

it('ignores the simulate trigger entirely when failure_simulation is disabled — double-gated, not just a validation rule', function () {
    config(['commerce.shipping.fake.failure_simulation.enabled' => false]);

    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    $fulfillmentId = readyFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]]);

    // 'simulate' isn't a validated field at all when the flag is off
    // (CompleteFulfillmentRequest), so a plain $request->validate() call
    // simply drops it rather than rejecting the request outright — the
    // real guarantee is FakeShippingProvider's own independent check on
    // the same config flag, proven here by the completion succeeding
    // normally instead of failing.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => 'fake',
        'simulate' => FakeShippingProvider::SIMULATE_CREATION_FAILURE,
    ], tenantHeader($this->storeA))->assertOk()->assertJsonPath('data.status', 'completed');
});

it('simulates a shipment creation failure when failure_simulation is enabled and the trigger is requested', function () {
    config(['commerce.shipping.fake.failure_simulation.enabled' => true]);

    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    $fulfillmentId = readyFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]]);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => 'fake',
        'simulate' => FakeShippingProvider::SIMULATE_CREATION_FAILURE,
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'shipment_creation_failed');

    // The whole transaction rolled back — no Shipment row, Fulfillment
    // stays ready (not completed), reservation stays consumable.
    app(TenantContext::class)->scope($this->storeA, function () {
        expect(Shipment::query()->count())->toBe(0);
    });

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/fulfillments/{$fulfillmentId}", tenantHeader($this->storeA))
        ->assertOk()->assertJsonPath('data.status', 'ready');
});

it('simulates a shipment creation timeout the same way', function () {
    config(['commerce.shipping.fake.failure_simulation.enabled' => true]);

    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    $fulfillmentId = readyFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]]);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => 'fake',
        'simulate' => FakeShippingProvider::SIMULATE_CREATION_TIMEOUT,
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'shipment_creation_failed');
});

it('a real completion still succeeds when failure_simulation is enabled but no trigger is requested', function () {
    config(['commerce.shipping.fake.failure_simulation.enabled' => true]);

    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    $fulfillmentId = readyFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]]);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => 'fake',
    ], tenantHeader($this->storeA))->assertOk()->assertJsonPath('data.status', 'completed');
});
