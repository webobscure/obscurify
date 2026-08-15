<?php

use App\Domain\Customers\Models\Customer;
use App\Domain\Orders\Models\Order;
use App\Domain\Returns\Models\ReturnRequest;
use App\Models\User;
use App\Shared\Commerce\Application\RecordOutboxEvent;
use App\Shared\Tenancy\TenantContext;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->customer, $this->order] = app(TenantContext::class)->scope($this->store, function () {
        $customer = Customer::factory()->create(['email' => 'admin-view@example.test']);
        $order = Order::factory()->create(['customer_id' => $customer->id]);

        app(RecordOutboxEvent::class)->handle('CustomerCreated', 'Customer', $customer->id, [
            'customer_id' => $customer->id,
            'store_id' => $customer->store_id,
        ]);

        return [$customer, $order];
    });
});

it('lists customers for the admin', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson('/api/v1/customers', tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.0.email', 'admin-view@example.test');
});

it('shows customer detail, orders, and activity timeline for the admin', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$this->customer->id}", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.email', 'admin-view@example.test');

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$this->customer->id}/orders", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->order->id);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$this->customer->id}/activity", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonPath('data.0.event_type', 'CustomerCreated');
});

it('shows an empty returns list when the customer has none, and a populated one after a return exists', function () {
    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$this->customer->id}/returns", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    app(TenantContext::class)->scope($this->store, function () {
        ReturnRequest::query()->create([
            'order_id' => $this->order->id,
            'customer_id' => $this->customer->id,
            'number' => 1,
            'status' => 'requested',
            'requested_at' => now(),
        ]);
    });

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$this->customer->id}/returns", tenantHeader($this->store))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('requires merchant admin authentication for customer management endpoints', function () {
    $this->getJson('/api/v1/customers', tenantHeader($this->store))->assertStatus(401);
});

it('never returns another stores customer even for an authenticated admin of a different store', function () {
    $otherStore = createStoreForUser($this->user, ['slug' => 'admin-other-store']);

    $this->actingAs($this->user, 'sanctum')
        ->getJson("/api/v1/customers/{$this->customer->id}", tenantHeader($otherStore))
        ->assertStatus(404);
});
