<?php

namespace App\Domain\Inventory\Application;

use App\Domain\Checkouts\Models\Checkout;
use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryReservation;
use App\Domain\Locations\Enums\LocationStatus;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Reserves stock for one InventoryItem, split deterministically across
 * active Locations (ordered by id) until the requested quantity is
 * satisfied — see "Multi-location allocation strategy" in the milestone
 * report for why split allocation was chosen over requiring one location.
 *
 * Must be called from inside an already-open DB transaction (always
 * CompleteCheckout's) — it does not open its own, so a failure here
 * (insufficient stock) rolls back everything the caller has done so far,
 * including the InventoryLevel.reserved increments this method itself
 * just made. It does not manage on_hand: reservation only ever moves
 * `reserved`, never physical stock — see ARCHITECTURE/milestone spec
 * section 16.
 */
final class ReserveInventory
{
    /**
     * @return Collection<int, InventoryReservation>
     */
    public function handle(InventoryItem $item, int $quantity, Checkout $checkout): Collection
    {
        if (! $item->tracked) {
            return new Collection;
        }

        $levels = InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->whereHas('location', fn ($query) => $query->where('status', LocationStatus::Active))
            ->orderBy('location_id')
            ->lockForUpdate()
            ->get();

        $remaining = $quantity;
        $reservations = new Collection;
        $ttl = (int) config('commerce.checkout.reservation_ttl');

        foreach ($levels as $level) {
            if ($remaining <= 0) {
                break;
            }

            $available = $level->on_hand - $level->reserved;

            if ($available <= 0) {
                continue;
            }

            $take = min($available, $remaining);

            $level->update(['reserved' => $level->reserved + $take]);

            $reservations->push(InventoryReservation::query()->create([
                'inventory_item_id' => $item->id,
                'location_id' => $level->location_id,
                'checkout_id' => $checkout->id,
                'quantity' => $take,
                'status' => ReservationStatus::Active->value,
                'expires_at' => now()->addMinutes($ttl),
            ]));

            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'quantity' => 'Not enough stock available.',
            ]);
        }

        return $reservations;
    }
}
