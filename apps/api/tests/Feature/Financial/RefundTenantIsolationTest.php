<?php

use App\Domain\Financial\Models\Refund;
use App\Domain\Payments\Models\Payment;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;

/**
 * Store A cannot reach Store B's Refund, RefundItem, Payment, or Ledger
 * through any route (spec section 17) — every check below uses a real
 * route, not a direct model query, since BelongsToTenant's scope is what's
 * actually under test here.
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

    ['order_id' => $orderIdB, 'return_item_id' => $returnItemIdB] = completedReturnFor('store-b.localhost', $this->userB, $this->storeB, $this->variantB->id, 1);
    $this->orderIdB = $orderIdB;

    $paymentB = app(TenantContext::class)->scope($this->storeB, fn () => Payment::query()->where('order_id', $orderIdB)->firstOrFail());

    $this->refundB = $this->actingAs($this->userB, 'sanctum')->postJson("/api/v1/orders/{$orderIdB}/refunds", [
        'items' => [['return_item_id' => $returnItemIdB, 'quantity' => 1, 'amount' => $paymentB->amount]],
    ], array_merge(tenantHeader($this->storeB), ['Idempotency-Key' => 'tenant-refund-b-1']))->assertCreated();

    $this->refundIdB = $this->refundB->json('data.id');
});

it('never lets Store A list or view Store B refunds', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/refunds', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/refunds/{$this->refundIdB}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('never lets Store A cancel a Store B refund', function () {
    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/refunds/{$this->refundIdB}/cancel", [], tenantHeader($this->storeA))
        ->assertNotFound();

    app(TenantContext::class)->scope($this->storeB, function () {
        expect(Refund::query()->whereKey($this->refundIdB)->firstOrFail()->status->value)->toBe('completed');
    });
});

it('never lets Store A request a refund against a Store B order', function () {
    [$this->productA, $this->variantA] = productWithStock($this->storeA, 10);
    ['return_item_id' => $returnItemIdB2] = completedReturnFor('store-b.localhost', $this->userB, $this->storeB, $this->variantB->id, 1);

    $this->actingAs($this->userA, 'sanctum')->postJson("/api/v1/orders/{$this->orderIdB}/refunds", [
        'items' => [['return_item_id' => $returnItemIdB2, 'quantity' => 1, 'amount' => 100]],
    ], array_merge(tenantHeader($this->storeA), ['Idempotency-Key' => 'tenant-refund-a-attempt-1']))->assertNotFound();
});

it('never lets Store A see or list Store B payments', function () {
    $paymentB = app(TenantContext::class)->scope($this->storeB, fn () => Payment::query()->where('order_id', $this->orderIdB)->firstOrFail());

    $this->actingAs($this->userA, 'sanctum')->getJson('/api/v1/payments', tenantHeader($this->storeA))
        ->assertOk()->assertJsonCount(0, 'data');

    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/payments/{$paymentB->id}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('never lets Store A see Store B ledger/financial data through the order page', function () {
    $this->actingAs($this->userA, 'sanctum')->getJson("/api/v1/orders/{$this->orderIdB}", tenantHeader($this->storeA))
        ->assertNotFound();
});
