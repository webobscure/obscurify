<?php

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Inventory\Application\ReleaseExpiredReservations;
use App\Domain\Inventory\Application\ReserveInventory;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Models\Location;
use App\Models\User;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->userA = User::factory()->create();
    $this->storeA = createStoreForUser($this->userA);

    $this->userB = User::factory()->create();
    $this->storeB = createStoreForUser($this->userB);
});

it('reserves from a single location when it alone covers the quantity', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $item = InventoryItem::factory()->create(['tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 10, 'reserved' => 0]);
        $checkout = Checkout::factory()->create();

        $reservations = DB::transaction(fn () => app(ReserveInventory::class)->handle($item, 4, $checkout));

        expect($reservations)->toHaveCount(1)
            ->and($reservations->first()->quantity)->toBe(4)
            ->and($reservations->first()->location_id)->toBe($location->id)
            ->and($reservations->first()->status)->toBe(ReservationStatus::Active);

        $level = InventoryLevel::query()->where('inventory_item_id', $item->id)->firstOrFail();
        expect($level->on_hand)->toBe(10)
            ->and($level->reserved)->toBe(4);
    });
});

it('splits allocation deterministically across locations in id order when one alone is not enough', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $item = InventoryItem::factory()->create(['tracked' => true]);

        $locationA = Location::factory()->create();
        $locationB = Location::factory()->create();
        // Guarantee ordering regardless of ULID generation timing.
        [$first, $second] = $locationA->id < $locationB->id ? [$locationA, $locationB] : [$locationB, $locationA];

        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $first->id, 'on_hand' => 3, 'reserved' => 0]);
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $second->id, 'on_hand' => 5, 'reserved' => 0]);

        $checkout = Checkout::factory()->create();

        $reservations = DB::transaction(fn () => app(ReserveInventory::class)->handle($item, 5, $checkout));

        expect($reservations)->toHaveCount(2);

        $byLocation = $reservations->keyBy('location_id');
        expect($byLocation->get($first->id)->quantity)->toBe(3)
            ->and($byLocation->get($second->id)->quantity)->toBe(2);

        $levels = InventoryLevel::query()->where('inventory_item_id', $item->id)->get()->keyBy('location_id');
        expect($levels->get($first->id)->reserved)->toBe(3)
            ->and($levels->get($second->id)->reserved)->toBe(2)
            ->and($levels->get($first->id)->on_hand)->toBe(3)
            ->and($levels->get($second->id)->on_hand)->toBe(5);
    });
});

it('rejects and leaves no partial reservation when total stock across all locations is insufficient', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $item = InventoryItem::factory()->create(['tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 3, 'reserved' => 0]);
        $checkout = Checkout::factory()->create();

        expect(fn () => DB::transaction(fn () => app(ReserveInventory::class)->handle($item, 10, $checkout)))
            ->toThrow(ValidationException::class);

        // The enclosing transaction rolled back, so even the reserved
        // increment ReserveInventory made against the one location it did
        // touch before running out is undone — no partial reservation.
        expect(InventoryReservation::query()->count())->toBe(0);
        $level = InventoryLevel::query()->where('inventory_item_id', $item->id)->firstOrFail();
        expect($level->reserved)->toBe(0);
    });
});

it('does not reserve anything for an untracked item', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $item = InventoryItem::factory()->create(['tracked' => false]);
        $checkout = Checkout::factory()->create();

        $reservations = DB::transaction(fn () => app(ReserveInventory::class)->handle($item, 1000, $checkout));

        expect($reservations)->toHaveCount(0);
    });
});

it('finds no stock for a Store B inventory item while Store A is active, and rejects', function () {
    $itemB = app(TenantContext::class)->scope($this->storeB, function () {
        $item = InventoryItem::factory()->create(['tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 50, 'reserved' => 0]);

        return $item;
    });

    app(TenantContext::class)->scope($this->storeA, function () use ($itemB) {
        $checkout = Checkout::factory()->create();

        // InventoryLevel::class is tenant-scoped: under Store A's active
        // context, Store B's levels for this item are invisible, so this
        // fails as "not enough stock" rather than ever touching them.
        expect(fn () => DB::transaction(fn () => app(ReserveInventory::class)->handle($itemB, 1, $checkout)))
            ->toThrow(ValidationException::class);
    });
});

it('releases an expired active reservation, decrements reserved, and is idempotent on a second run', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $item = InventoryItem::factory()->create(['tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 10, 'reserved' => 4]);

        $reservation = InventoryReservation::factory()->create([
            'inventory_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 4,
            'status' => ReservationStatus::Active,
            'expires_at' => now()->subMinute(),
        ]);

        $released = app(ReleaseExpiredReservations::class)->handle();
        expect($released)->toBe(1);

        $reservation->refresh();
        expect($reservation->status)->toBe(ReservationStatus::Expired)
            ->and($reservation->released_at)->not->toBeNull();

        $level = InventoryLevel::query()->where('inventory_item_id', $item->id)->firstOrFail();
        expect($level->on_hand)->toBe(10)
            ->and($level->reserved)->toBe(0);

        // Running it again must not double-decrement reserved.
        $releasedAgain = app(ReleaseExpiredReservations::class)->handle();
        expect($releasedAgain)->toBe(0);

        $level->refresh();
        expect($level->reserved)->toBe(0);
    });
});

it('does not release a reservation that has not expired yet', function () {
    app(TenantContext::class)->scope($this->storeA, function () {
        $item = InventoryItem::factory()->create(['tracked' => true]);
        $location = Location::factory()->create();
        InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 10, 'reserved' => 2]);

        $reservation = InventoryReservation::factory()->create([
            'inventory_item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 2,
            'status' => ReservationStatus::Active,
            'expires_at' => now()->addMinutes(30),
        ]);

        expect(app(ReleaseExpiredReservations::class)->handle())->toBe(0);

        $reservation->refresh();
        expect($reservation->status)->toBe(ReservationStatus::Active);
    });
});

it('releases expired reservations across every store in a single sweep', function () {
    $setup = function ($store) {
        return app(TenantContext::class)->scope($store, function () {
            $item = InventoryItem::factory()->create(['tracked' => true]);
            $location = Location::factory()->create();
            InventoryLevel::factory()->create(['inventory_item_id' => $item->id, 'location_id' => $location->id, 'on_hand' => 5, 'reserved' => 1]);

            return InventoryReservation::factory()->create([
                'inventory_item_id' => $item->id,
                'location_id' => $location->id,
                'quantity' => 1,
                'status' => ReservationStatus::Active,
                'expires_at' => now()->subMinute(),
            ]);
        });
    };

    $reservationA = $setup($this->storeA);
    $reservationB = $setup($this->storeB);

    expect(app(ReleaseExpiredReservations::class)->handle())->toBe(2);

    app(TenantContext::class)->scope($this->storeA, fn () => expect($reservationA->refresh()->status)->toBe(ReservationStatus::Expired));
    app(TenantContext::class)->scope($this->storeB, fn () => expect($reservationB->refresh()->status)->toBe(ReservationStatus::Expired));
});
