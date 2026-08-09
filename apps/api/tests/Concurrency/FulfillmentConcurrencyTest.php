<?php

use App\Domain\Fulfillment\Application\AllocateFulfillment;
use App\Domain\Fulfillment\Application\CreateFulfillment;
use App\Domain\Fulfillment\Models\Fulfillment;
use App\Domain\Fulfillment\Models\FulfillmentAllocation;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Models\Location;
use App\Domain\Orders\Enums\FinancialStatus;
use App\Domain\Orders\Models\Order;
use App\Domain\Orders\Models\OrderItem;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Milestone 7 (spec section 19): "two concurrent fulfillments cannot
 * allocate [the] same reservation" and "cannot overship." Two Fulfillments
 * are created against an OrderItem large enough that both individually
 * pass the ordered-quantity ceiling (CreateFulfillment), but the
 * underlying InventoryReservation only covers half of what the two
 * Fulfillments together want — exactly the scenario AllocateFulfillment's
 * row lock on the reservation exists for.
 */
beforeEach(function () {
    $this->user = User::factory()->create();
    $this->store = createStoreForUser($this->user);

    [$this->fulfillmentAId, $this->fulfillmentBId, $this->reservationId] = app(TenantContext::class)->scope($this->store, function () {
        $order = Order::factory()->create(['financial_status' => FinancialStatus::Paid]);
        // Ordered=4 so two Fulfillments of 2 each both pass CreateFulfillment's
        // ordered-quantity ceiling — the contention is only at allocation.
        $item = OrderItem::factory()->create(['order_id' => $order->id, 'quantity' => 4]);

        $location = Location::factory()->create();
        $inventoryItem = InventoryItem::factory()->create();
        InventoryLevel::factory()->create([
            'inventory_item_id' => $inventoryItem->id,
            'location_id' => $location->id,
            'on_hand' => 2,
            'reserved' => 2,
        ]);
        // Only 2 units actually reserved — not enough for both Fulfillments'
        // 2 + 2 = 4 request, simulating a reservation smaller than a later
        // partial-fulfillment split would want.
        $reservation = InventoryReservation::factory()->create([
            'inventory_item_id' => $inventoryItem->id,
            'location_id' => $location->id,
            'order_id' => $order->id,
            'quantity' => 2,
        ]);

        $item->update(['product_variant_id' => $inventoryItem->product_variant_id]);

        $fulfillmentA = app(CreateFulfillment::class)->handle($order, [['order_item_id' => $item->id, 'quantity' => 2]], null, null);
        $fulfillmentB = app(CreateFulfillment::class)->handle($order, [['order_item_id' => $item->id, 'quantity' => 2]], null, null);

        return [$fulfillmentA->id, $fulfillmentB->id, $reservation->id];
    });
});

afterEach(function () {
    DB::table('stores')->where('id', $this->store->id)->delete();
    DB::table('users')->where('id', $this->user->id)->delete();
});

it('lets exactly one of two simultaneous allocations claim a scarce reservation, never oversubscribing it', function () {
    $allocate = function (string $fulfillmentId) {
        return app(TenantContext::class)->scope($this->store, function () use ($fulfillmentId) {
            $fulfillment = Fulfillment::query()->whereKey($fulfillmentId)->firstOrFail();

            $fulfillment = app(AllocateFulfillment::class)->handle($fulfillment);

            return $fulfillment->status->value;
        });
    };

    $results = runConcurrently([
        fn () => $allocate($this->fulfillmentAId),
        fn () => $allocate($this->fulfillmentBId),
    ]);

    $succeeded = array_filter($results, fn ($r) => $r['ok']);
    $failed = array_filter($results, fn ($r) => ! $r['ok']);

    expect($succeeded)->toHaveCount(1)
        ->and($failed)->toHaveCount(1);

    expect(current($failed)['error'])->toContain('reserved');

    app(TenantContext::class)->scope($this->store, function () {
        // Never oversubscribed: total non-cancelled allocation quantity
        // against this reservation never exceeds what was actually
        // reserved (2), regardless of which Fulfillment won the race.
        $allocatedTotal = (int) FulfillmentAllocation::query()
            ->where('inventory_reservation_id', $this->reservationId)
            ->whereNull('cancelled_at')
            ->sum('quantity');

        expect($allocatedTotal)->toBe(2);

        $statuses = Fulfillment::query()
            ->whereIn('id', [$this->fulfillmentAId, $this->fulfillmentBId])
            ->pluck('status')
            ->map(fn ($s) => $s->value)
            ->sort()
            ->values()
            ->all();

        expect($statuses)->toBe(['allocated', 'pending']);
    });
});
