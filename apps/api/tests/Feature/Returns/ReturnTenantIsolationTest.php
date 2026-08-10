<?php

use App\Domain\Returns\Models\ReturnRequest;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Store A cannot reach Store B's ReturnRequest, ReturnItem, Inspection,
 * Disposition, or timeline through any route (spec section 15) — every
 * check below uses a real route, not a direct model query, since
 * BelongsToTenant's scope is what's actually under test here.
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
    shipViaFulfillment($this->userB, $this->storeB, $orderIdB, [['order_item_id' => $orderItemIdB, 'quantity' => 2]])->assertOk();

    $this->returnB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/orders/{$orderIdB}/returns", [
        'items' => [['order_item_id' => $orderItemIdB, 'quantity' => 2, 'reason' => 'other']],
    ], tenantHeader($this->storeB))->assertCreated();

    $this->returnIdB = $this->returnB->json('data.id');
    $this->returnItemIdB = $this->returnB->json('data.items.0.id');
});

it('never lets Store A list or view Store B returns', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/returns', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/returns/{$this->returnIdB}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('never lets Store A act on a Store B return via any state-changing route', function () {
    $headers = tenantHeader($this->storeA);

    $this->actingAs($this->userA, 'sanctum')->patchJson("/api/v1/returns/{$this->returnIdB}", ['notes' => 'x'], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$this->returnIdB}/approve", [], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$this->returnIdB}/reject", [], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$this->returnIdB}/receive", [], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$this->returnIdB}/inspect", [
        'items' => [['return_item_id' => $this->returnItemIdB, 'condition' => 'new', 'disposition' => 'restock']],
    ], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$this->returnIdB}/complete", [], $headers)->assertNotFound();
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/returns/{$this->returnIdB}/cancel", [], $headers)->assertNotFound();

    // Confirmed unaffected: Store B's return is exactly as it was.
    app(TenantContext::class)->scope($this->storeB, function () {
        expect(ReturnRequest::query()->whereKey($this->returnIdB)->firstOrFail()->status->value)->toBe('requested');
    });
});

it('never lets Store A request a return against a Store B order', function () {
    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
    ['order_id' => $orderIdB, 'order_item_id' => $orderItemIdB] = paidOrderFor('store-b.localhost', $this->variantB->id, $this->storeB);
    shipViaFulfillment($this->userB, $this->storeB, $orderIdB, [['order_item_id' => $orderItemIdB, 'quantity' => 1]])->assertOk();

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderIdB}/returns", [
        'items' => [['order_item_id' => $orderItemIdB, 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertNotFound();
});

it('never lets a Store A return reference a Store B OrderItem', function () {
    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
    ['order_id' => $orderIdA, 'order_item_id' => $orderItemIdA] = paidOrderFor('store-a.localhost', $this->variantA->id, $this->storeA);
    shipViaFulfillment($this->userA, $this->storeA, $orderIdA, [['order_item_id' => $orderItemIdA, 'quantity' => 1]])->assertOk();

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$orderIdA}/returns", [
        'items' => [['order_item_id' => $this->returnB->json('data.items.0.order_item_id'), 'quantity' => 1, 'reason' => 'other']],
    ], tenantHeader($this->storeA))->assertStatus(422);
});
