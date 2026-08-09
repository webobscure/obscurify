<?php

use App\Domain\Fulfillment\Application\AllocateFulfillment;
use App\Domain\Fulfillment\Application\CreateFulfillment;
use App\Domain\Fulfillment\Application\PackFulfillmentItems;
use App\Domain\Fulfillment\Application\PickFulfillmentItems;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Domain\Shipping\Application\CreateShipment;
use App\Domain\Shipping\Models\Shipment;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Milestone 7 (spec section 19): "two concurrent fulfillments cannot
 * double-consume inventory." Since Shipping\Application\CreateShipment is
 * what triggers reservation consumption (Fulfillment\Application\
 * CompleteFulfillment, called at the end of a successful shipment
 * creation), the concurrency-critical race lives here — two simultaneous
 * attempts to create a Shipment against the *same* ready Fulfillment must
 * let exactly one through and consume stock exactly once.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    $this->fulfillmentId = app(TenantContext::class)->scope($this->store, function () {
        $order = Order::factory()->create(['financial_status' => FinancialStatus::Paid]);
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 2]);

        $location = Location::factory()->create();
        $inventoryItem = InventoryItem::factory()->create();
        InventoryLevel::factory()->create([
            'inventory_item_id' => $inventoryItem->id,
            'location_id' => $location->id,
            'on_hand' => 5,
            'reserved' => 2,
        ]);
        $reservation = InventoryReservation::factory()->create([
            'inventory_item_id' => $inventoryItem->id,
            'location_id' => $location->id,
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        $item->update(['product_variant_id' => $inventoryItem->product_variant_id]);

        $fulfillment = app(CreateFulfillment::class)->handle(
            $order,
            [['order_item_id' => $item->id, 'quantity' => 2]],
            null,
            null,
        );
        $fulfillment = app(AllocateFulfillment::class)->handle($fulfillment);

        $items = $fulfillment->items->map(fn ($fi) => ['fulfillment_item_id' => $fi->id, 'picked_quantity' => $fi->quantity])->all();
        $fulfillment = app(PickFulfillmentItems::class)->handle($fulfillment, $items);

        $items = $fulfillment->items->map(fn ($fi) => ['fulfillment_item_id' => $fi->id, 'packed_quantity' => $fi->quantity])->all();
        $fulfillment = app(PackFulfillmentItems::class)->handle($fulfillment, $items);

        expect($fulfillment->status->value)->toBe('ready');
        expect($reservation->quantity)->toBe(2);

        return $fulfillment->id;
    });
});

afterEach(function () {
    // Genuinely committed rows (no RefreshDatabase in this suite — see
    // Pest.php); deleting the store cascades everything else.
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous shipment-create requests for the same Fulfillment win, never double-consuming inventory', function () {
    $createShipment = function () {
        return app(TenantContext::class)->scope($this->store, function () {
            $fulfillment = Fulfillment::query()->whereKey($this->fulfillmentId)->firstOrFail();

            $shipment = app(CreateShipment::class)->handle($fulfillment, 'fake');

            return $shipment->id;
        });
    };

    $results = runConcurrently([$createShipment, $createShipment]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    app(TenantContext::class)->scope($this->store, function () use ($succeeded) {
        expect(Shipment::query()->count())->toBe(1)
            ->and(Shipment::query()->firstOrFail()->id)->toBe(current($succeeded)['value']);

        $fulfillment = Fulfillment::query()->whereKey($this->fulfillmentId)->firstOrFail();
        expect($fulfillment->status->value)->toBe('completed');

        // Consumption happened exactly once: on_hand started at 5, exactly
        // 2 units were consumed (never 4, which double-consumption would
        // produce), reserved dropped by the same 2.
        $level = InventoryLevel::query()->firstOrFail();
        expect($level->on_hand)->toBe(3)
            ->and($level->reserved)->toBe(0);

        $reservation = InventoryReservation::query()->firstOrFail();
        expect($reservation->status->value)->toBe('consumed');
    });
});
