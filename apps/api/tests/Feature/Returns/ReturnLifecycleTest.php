<?php

use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Models\ReturnDisposition;
use App\Domain\Returns\Models\ReturnInspection;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
});

it('walks a return through requested -> approved -> awaiting_return -> received -> inspection -> completed, restocking inventory exactly once', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 3);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 3]])->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(7); // 10 - 3 shipped/consumed
    });

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => 'wrong_size', 'condition' => 'new']],
    ], tenantHeader($this->storeA))->assertCreated();

    expect($created->json('data.status'))->toBe('requested')
        ->and($created->json('data.number'))->toBeGreaterThanOrEqual(1001)
        ->and($created->json('data.items.0.quantity'))->toBe(2)
        ->and($created->json('data.items.0.reason'))->toBe('wrong_size')
        ->and($created->json('data.events.0.type'))->toBe('requested');

    $returnId = $created->json('data.id');
    $returnItemId = $created->json('data.items.0.id');

    $approved = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve", [], tenantHeader($this->storeA))
        ->assertOk();

    // Auto-advances straight to awaiting_return in the same call — no
    // separate endpoint for that hop (see ReturnStateMachine).
    expect($approved->json('data.status'))->toBe('awaiting_return')
        ->and($approved->json('data.approved_at'))->not->toBeNull();

    $received = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($received->json('data.status'))->toBe('received')
        ->and($received->json('data.received_at'))->not->toBeNull();

    // Receiving records a zero-delta audit movement — inventory must not
    // move yet (spec section 8: only after inspection).
    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(7);

        $movement = InventoryMovement::query()->where('reason', 'return_received')->firstOrFail();
        expect($movement->quantity_delta)->toBe(0);
    });

    $inspected = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/inspect", [
        'items' => [[
            'return_item_id' => $returnItemId,
            'condition' => 'new',
            'notes' => 'Unopened, resellable.',
            'disposition' => 'restock',
        ]],
    ], tenantHeader($this->storeA))->assertOk();

    expect($inspected->json('data.status'))->toBe('inspection')
        ->and($inspected->json('data.items.0.inspection.condition'))->toBe('new')
        ->and($inspected->json('data.items.0.disposition.disposition'))->toBe('restock')
        ->and($inspected->json('data.items.0.disposition.applied_at'))->toBeNull();

    // Disposition decided but not applied yet — on_hand still untouched.
    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(7);
    });

    $completed = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/complete", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($completed->json('data.status'))->toBe('completed')
        ->and($completed->json('data.closed_at'))->not->toBeNull()
        ->and($completed->json('data.items.0.disposition.applied_at'))->not->toBeNull();

    app(TenantContext::class)->scope($this->storeA, function () use ($orderId) {
        // The one real positive delta this domain writes: on_hand goes
        // back up by exactly the restocked quantity (2), never more.
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(9); // 7 + 2 restocked

        expect((int) InventoryMovement::query()->where('reason', 'return_restocked')->sum('quantity_delta'))->toBe(2);

        // Order itself is untouched by a return completing — this
        // milestone is explicitly not about refunds/order-status changes.
        $order = Order::query()->whereKey($orderId)->firstOrFail();
        expect($order->order_status->value)->not->toBe('cancelled');
    });
});

it('never restocks a damaged or discarded item, but still records the movement and closes the return', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 2);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [
            ['order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => 'damaged'],
        ],
    ], tenantHeader($this->storeA))->assertCreated();

    $returnId = $created->json('data.id');
    $returnItemId = $created->json('data.items.0.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve", [], tenantHeader($this->storeA))->assertOk();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive", [], tenantHeader($this->storeA))->assertOk();

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/inspect", [
        'items' => [[
            'return_item_id' => $returnItemId,
            'condition' => 'damaged',
            'disposition' => 'damaged',
        ]],
    ], tenantHeader($this->storeA))->assertOk();

    app(TenantContext::class)->scope($this->storeA, function () {
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(8); // 10 - 2 shipped
    });

    $completed = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/complete", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($completed->json('data.status'))->toBe('completed');

    app(TenantContext::class)->scope($this->storeA, function () {
        // Never sellable again: on_hand unchanged by a damaged disposition.
        expect(InventoryLevel::query()->firstOrFail()->on_hand)->toBe(8);

        $movement = InventoryMovement::query()->where('reason', 'return_damaged')->firstOrFail();
        expect($movement->quantity_delta)->toBe(0);
    });
});

it('rejects a return request that exceeds the shipped-minus-already-returned quantity', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 2);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();

    // Nothing has shipped for a fresh order (0 shipped) — any positive
    // quantity against an unshipped OrderItem is rejected too.
    [$productB, $variantB] = productWithStock($this->storeA, 10);
    ['order_id' => $unshippedOrderId, 'order_item_id' => $unshippedItemId] = paidOrderFor('store-a.localhost', $variantB->id, $this->storeA, 1);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$unshippedOrderId}/returns", [
        'items' => [['order_item_id' => $unshippedItemId, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'return_over_receipt');

    // Requesting more than was shipped is rejected.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 3, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'return_over_receipt');

    // A first return for all 2 shipped units succeeds...
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertCreated();

    // ...and a second return against the same, now-fully-claimed OrderItem
    // is rejected — nothing left to return.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertStatus(422)->assertJsonPath('error', 'return_over_receipt');
});

it('rejects a return, and the returnable quantity becomes claimable again', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 2);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 2]])->assertOk();

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => 'ordered_by_mistake']],
    ], tenantHeader($this->storeA))->assertCreated();

    $returnId = $created->json('data.id');

    $rejected = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/reject", [
        'reason' => 'Outside the return window.',
    ], tenantHeader($this->storeA))->assertOk();

    expect($rejected->json('data.status'))->toBe('rejected')
        ->and($rejected->json('data.closed_at'))->not->toBeNull()
        ->and($rejected->json('data.events.1.description'))->toBe('Outside the return window.');

    // A rejected return does not consume the returnable budget — a fresh
    // request for the same quantity succeeds.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 2, 'reason' => 'ordered_by_mistake']],
    ], tenantHeader($this->storeA))->assertCreated();
});

it('rejects transitions that skip a lifecycle step', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 1);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]])->assertOk();

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertCreated();

    $returnId = $created->json('data.id');
    $returnItemId = $created->json('data.items.0.id');

    // Cannot receive before approving.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive", [], tenantHeader($this->storeA))
        ->assertStatus(409)->assertJsonPath('error', 'invalid_return_transition');

    // Cannot inspect before receiving — a syntactically valid items array,
    // so this genuinely exercises the state machine guard rather than
    // failing FormRequest validation first.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/inspect", [
        'items' => [['return_item_id' => $returnItemId, 'condition' => 'new', 'disposition' => 'restock']],
    ], tenantHeader($this->storeA))->assertStatus(409)->assertJsonPath('error', 'invalid_return_transition');

    // Cannot complete before inspecting.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/complete", [], tenantHeader($this->storeA))
        ->assertStatus(409)->assertJsonPath('error', 'invalid_return_transition');
});

it('rejects inspecting the same return item twice', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 1);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]])->assertOk();

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertCreated();

    $returnId = $created->json('data.id');
    $returnItemId = $created->json('data.items.0.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve", [], tenantHeader($this->storeA))->assertOk();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/receive", [], tenantHeader($this->storeA))->assertOk();

    $inspectPayload = [
        'items' => [['return_item_id' => $returnItemId, 'condition' => 'new', 'disposition' => 'restock']],
    ];

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/inspect", $inspectPayload, tenantHeader($this->storeA))->assertOk();

    // A second inspect call re-running the same status transition is
    // otherwise a no-op, but re-inspecting the same item is rejected.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/inspect", $inspectPayload, tenantHeader($this->storeA))
        ->assertStatus(422);

    app(TenantContext::class)->scope($this->storeA, function () use ($returnItemId) {
        expect(ReturnInspection::query()->where('return_item_id', $returnItemId)->count())->toBe(1);
        expect(ReturnDisposition::query()->where('return_item_id', $returnItemId)->count())->toBe(1);
    });
});

it('cancels a return from a non-terminal state', function () {
    ['order_id' => $orderId, 'order_item_id' => $orderItemId] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA, 1);
    shipViaFulfillment($this->userA, $this->storeA, $orderId, [['order_item_id' => $orderItemId, 'quantity' => 1]])->assertOk();

    $created = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertCreated();

    $returnId = $created->json('data.id');

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/approve", [], tenantHeader($this->storeA))->assertOk();

    $cancelled = $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$returnId}/cancel", [], tenantHeader($this->storeA))
        ->assertOk();

    expect($cancelled->json('data.status'))->toBe('cancelled')
        ->and($cancelled->json('data.closed_at'))->not->toBeNull();

    // A cancelled return does not consume the returnable budget either.
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderId}/returns", [
        'items' => [['order_item_id' => $orderItemId, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertCreated();
});
