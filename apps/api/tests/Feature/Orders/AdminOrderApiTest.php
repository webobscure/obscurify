<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Enums\FulfillmentStatus;
use App\Domain\Orders\Enums\OrderStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderAddress;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Stores\Models\Store;
use App\Models\User;
use App\Shared\Commerce\Enums\AddressType;
use App\Shared\Tenancy\TenantContext;

/**
 * @return array{0: Order, 1: Customer}
 */
function orderWithDetails(Store $store, int $number): array
{
    return app(TenantContext::class)->scope($store, function () use ($number) {
        $customer = Customer::factory()->create();

        $order = Order::factory()->create([
            'number' => $number,
            'customer_id' => $customer->id,
            'order_status' => OrderStatus::Open,
            'financial_status' => FinancialStatus::Pending,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
        ]);

        OrderItem::factory()->create(['order_id' => $order->id]);
        OrderAddress::factory()->create(['order_id' => $order->id, 'type' => AddressType::Shipping]);
        OrderAddress::factory()->create(['order_id' => $order->id, 'type' => AddressType::Billing]);

        return [$order, $customer];
    });
}

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);

    [$this->orderA, $this->customerA] = orderWithDetails($this->storeA, 5001);
    [$this->orderB] = orderWithDetails($this->storeB, 6001);
});

it('lists only the active store orders, no payment/refund/fulfillment affordances', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/orders', tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.id'))->toBe($this->orderA->id)
        ->and($response->json('data.0.number'))->toBe(5001)
        ->and($response->json('data.0.customer.id'))->toBe($this->customerA->id);

    $response->assertJsonMissing(['id' => $this->orderB->id]);
});

it('shows full order detail: items, addresses, statuses', function () {
    $response = $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/orders/{$this->orderA->id}", tenantHeader($this->storeA))
        ->assertOk();

    expect($response->json('data.order_status'))->toBe('open')
        ->and($response->json('data.financial_status'))->toBe('pending')
        ->and($response->json('data.fulfillment_status'))->toBe('unfulfilled')
        ->and($response->json('data.items'))->toHaveCount(1)
        ->and($response->json('data.shipping_address'))->not->toBeNull()
        ->and($response->json('data.billing_address'))->not->toBeNull();
});

it('does not let Store A list or view a Store B order', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/orders', tenantHeader($this->storeA))
        ->assertOk()
        ->assertJsonMissing(['id' => $this->orderB->id]);

    $this->actingAs($this->userA, 'sanctum')
        ->getJson("/api/v1/orders/{$this->orderB->id}", tenantHeader($this->storeA))
        ->assertNotFound();
});

it('rejects a request with no active store header', function () {
    $this->actingAs($this->userA, 'sanctum')
        ->getJson('/api/v1/orders')
        ->assertStatus(428);
});

it('rejects a user who is not a member of the requested store', function () {
    $this->actingAs($this->userB, 'sanctum')
        ->getJson('/api/v1/orders', tenantHeader($this->storeA))
        ->assertStatus(403);
});
