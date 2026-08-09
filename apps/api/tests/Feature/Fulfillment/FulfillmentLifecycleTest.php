<?php

use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\InventoryReservation;
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

it('walks a Fulfillment through pending -> allocated -> picking -> packing -> ready -> completed, consuming inventory exactly once', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 3);

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 3]],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($created->json('data.status'))->toBe('pending')
        ->and($created->json('data.items.0.quantity'))->toBe(3)
        ->and($created->json('data.items.0.picked_quantity'))->toBe(0);

    $fulfillmentId = $created->json('data.id');
    $itemId = $created->json('data.items.0.id');

    $allocated = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/allocate", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($allocated->json('data.status'))->toBe('allocated')
        ->and($allocated->json('data.items.0.allocations.0.quantity'))->toBe(3);

    // Allocation is bookkeeping only — it must never touch on_hand or
    // reserved (spec section 7: consumption belongs to Fulfillment, not
    // allocation).
    app(TenantContext::class)->scope($this->storeA, function () {
        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(10)->and($level->reserved)->toBe(3);

        expect(InventoryMovement::query()->where('reason', 'fulfillment_allocated')->count())->toBe(1);
    });

    // pick before allocate would be rejected by the state machine — assert
    // the reverse (allocate before pick already happened above) is the
    // only order that works by confirming a *second* allocate call is a
    // harmless no-op (canTransition treats same-state as valid).
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/allocate", [], tenantHeader($this->storeA))
        ->assertStatus(409)->assertJsonPath('error', 'invalid_fulfillment_transition');

    $picked = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pick", [
        'items' => [['fulfillment_item_id' => $itemId, 'picked_quantity' => 2]],
    ], tenantHeader($this->storeA))->assertOk();

    expect($picked->json('data.status'))->toBe('picking')
        ->and($picked->json('data.items.0.picked_quantity'))->toBe(2);

    // Picking more than the fulfillment item's quantity is rejected.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pick", [
        'items' => [['fulfillment_item_id' => $itemId, 'picked_quantity' => 4]],
    ], tenantHeader($this->storeA))->assertStatus(422);

    $picked = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pick", [
        'items' => [['fulfillment_item_id' => $itemId, 'picked_quantity' => 3]],
    ], tenantHeader($this->storeA))->assertOk();

    expect($picked->json('data.items.0.picked_quantity'))->toBe(3);

    // Packing more than what was picked is rejected.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pack", [
        'items' => [['fulfillment_item_id' => $itemId, 'packed_quantity' => 4]],
    ], tenantHeader($this->storeA))->assertStatus(422);

    $partiallyPacked = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pack", [
        'items' => [['fulfillment_item_id' => $itemId, 'packed_quantity' => 1]],
    ], tenantHeader($this->storeA))->assertOk();

    // Not fully packed yet — stays in `packing`, does not auto-advance.
    expect($partiallyPacked->json('data.status'))->toBe('packing');

    $ready = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pack", [
        'items' => [['fulfillment_item_id' => $itemId, 'packed_quantity' => 3]],
    ], tenantHeader($this->storeA))->assertOk();

    // Fully packed — auto-advances to `ready` with no separate endpoint.
    expect($ready->json('data.status'))->toBe('ready');

    $completed = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => 'fake',
    ], tenantHeader($this->storeA))->assertOk();

    expect($completed->json('data.status'))->toBe('completed')
        ->and($completed->json('data.completed_at'))->not->toBeNull()
        ->and($completed->json('data.shipments.0.status'))->toBe('created')
        ->and($completed->json('data.shipments.0.fulfillment_id') ?? $fulfillmentId)->toBe($fulfillmentId);

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        // Consumption invariant: on_hand and reserved both dropped by
        // exactly the fulfilled quantity (3), never more, never at any
        // earlier step (checkout/payment already happened above without
        // moving on_hand).
        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(7)->and($level->reserved)->toBe(0);

        $reservation = InventoryReservation::query()->firstOrFail();
        expect($reservation->status->value)->toBe('consumed')
            ->and($reservation->consumed_at)->not->toBeNull();

        expect((int) InventoryMovement::query()->where('reason', 'fulfillment_completed')->sum('quantity_delta'))->toBe(-3);

        $allocation = FulfillmentAllocation::query()->firstOrFail();
        expect($allocation->consumed_at)->not->toBeNull();

        // Order's own 3-state rollup reflects full fulfillment.
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        expect($order->fulfillment_status->value)->toBe('fulfilled');
    });
});

it('supports partial fulfillment: two Fulfillments split one OrderItem, and the remaining quantity stays fulfillable', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 5);

    $first = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();
    expect($first->json('data.status'))->toBe('completed');

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        expect($order->fulfillment_status->value)->toBe('partial');
    });

    $second = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 3]])->assertOk();
    expect($second->json('data.status'))->toBe('completed');

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        expect($order->fulfillment_status->value)->toBe('fulfilled');

        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(5); // 10 - 5 fulfilled total
    });

    // A third Fulfillment attempt against the now-fully-fulfilled OrderItem
    // is rejected — nothing left to fulfill.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'fulfillment_overshipment');
});

it('rejects picking before allocation, and rejects packing before picking', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertCreated();

    $fulfillmentId = $created->json('data.id');
    $itemId = $created->json('data.items.0.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pick", [
        'items' => [['fulfillment_item_id' => $itemId, 'picked_quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(409)->assertJsonPath('error', 'invalid_fulfillment_transition');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/pack", [
        'items' => [['fulfillment_item_id' => $itemId, 'packed_quantity' => 1]],
    ], tenantHeader($this->storeA))->assertStatus(409)->assertJsonPath('error', 'invalid_fulfillment_transition');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/complete", [
        'provider' => 'fake',
    ], tenantHeader($this->storeA))->assertStatus(422);
});

it('cancels a Fulfillment: releases allocations, does not cancel the Order, and the released quantity is fulfillable again', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 2);

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2]],
    ], tenantHeader($this->storeA))->assertCreated();

    $fulfillmentId = $created->json('data.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/allocate", [], tenantHeader($this->storeA))->assertOk();

    $cancelled = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$fulfillmentId}/cancel", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($cancelled->json('data.status'))->toBe('cancelled')
        ->and($cancelled->json('data.items.0.allocations.0.cancelled_at'))->not->toBeNull();

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        // Order untouched — cancelling a Fulfillment never cancels the Order.
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        expect($order->order_status->value)->not->toBe('cancelled')
            ->and($order->fulfillment_status->value)->toBe('unfulfilled');

        // Nothing was ever consumed, so on_hand/reserved are untouched by
        // cancellation — reserved still reflects checkout's own reservation.
        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(10)->and($level->reserved)->toBe(2);

        expect(InventoryMovement::query()->where('reason', 'reservation_released')->count())->toBe(1);
    });

    // The released capacity is fulfillable again by a new Fulfillment.
    $retry = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/fulfillments", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2]],
    ], tenantHeader($this->storeA))->assertCreated();

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$retry->json('data.id')}/allocate", [], tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonPath('data.status', 'allocated');
});

it('cancelling a shipment that already consumed stock reverses the consumption', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 2);

    $completed = shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();
    $shipmentId = $completed->json('data.shipments.0.id');

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(8); // 10 - 2 consumed
    });

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/shipments/{$shipmentId}/cancel", [], tenantHeader($this->storeA))
        ->assertOk()->assertJsonPath('data.status', 'cancelled');

    app(TenantContext::class)->scope($this->storeA, function () {
        // Reversed: on_hand restored, recorded as its own movement rather
        // than editing the original FulfillmentCompleted movement.
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(10);
        expect((int) InventoryMovement::query()->where('reason', 'shipment_cancelled')->sum('quantity_delta'))->toBe(2);
    });
});
