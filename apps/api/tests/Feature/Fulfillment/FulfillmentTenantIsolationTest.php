<?php

use App\Domain\Fulfillment\Models\Fulfillment;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Store A cannot reach Store B's Fulfillment, FulfillmentItem, Allocation,
 * or timeline through any route (spec section 20) — every check below
 * uses a real route, not a direct model query, since BelongsToTenant's
 * scope is what's actually under test here.
 */
beforeEach(function () {
    $this->withCredentials();

    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);
    domainForStore($this->storeA, 'store-a.localhost');

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
    domainForStore($this->storeB, 'store-b.localhost');

    [$this->productB, $this->variantB] = productWithStock($this->storeB, 10);

    ['order_id' => $orderIdB, 'order_item_id' => $orderItemIdB] = paidOrderFor('store-b.localhost', $this->variantB->id, $this->storeB, 2);

    $this->fulfillmentB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/orders/{$orderIdB}/fulfillments", [
        'items' => [['order_item_id' => $orderItemIdB, 'quantity' => 2]],
    ], tenantHeader($this->storeB))->assertCreated();

    $this->fulfillmentBId = $this->fulfillmentB->json('data.id');
});

it('never lets Store A list or view Store B fulfillments', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/fulfillments', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/fulfillments/{$this->fulfillmentBId}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('never lets Store A act on a Store B fulfillment via any state-changing route', function () {
    $headers = tenantHeader($this->storeA);

    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/fulfillments/{$this->fulfillmentBId}", ['notes' => 'x'], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$this->fulfillmentBId}/allocate", [], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$this->fulfillmentBId}/pick", ['items' => []], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$this->fulfillmentBId}/pack", ['items' => []], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$this->fulfillmentBId}/complete", ['provider' => 'fake'], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/fulfillments/{$this->fulfillmentBId}/cancel", [], $headers)->assertNotFound();

    // Confirmed unaffected: Store B's fulfillment is exactly as it was.
    app(TenantContext::class)->scope($this->storeB, function () {
        expect(Fulfillment::query()->whereKey($this->fulfillmentBId)->firstOrFail()->status->value)->toBe('pending');
    });
});

it('never lets Store A create a fulfillment against a Store B order', function () {
    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
    ['order_id' => $orderIdB, 'order_item_id' => $orderItemIdB] = paidOrderFor('store-b.localhost', $this->variantB->id, $this->storeB);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderIdB}/fulfillments", [
        'items' => [['order_item_id' => $orderItemIdB, 'quantity' => 1]],
    ], tenantHeader($this->storeA))->assertNotFound();
});
