<?php

namespace App\Domain\Inventory\Application;

use App\Domain\Inventory\Enums\ReservationStatus;
use App\Domain\Inventory\Models\InventoryLevel;
use App\Domain\Inventory\Models\InventoryReservation;
use Illuminate\Support\Facades\DB;

/**
 * Maintenance sweep across all stores — like PruneExpiredCartsCommand,
 * deliberately bypasses BelongsToTenant (see ARCHITECTURE.md section
 * 10.6) since expiry is a time-based fact, not a per-tenant business
 * operation.
 *
 * Each reservation is released in its own locked transaction so one
 * reservation failing (or racing another worker) never blocks the rest
 * of the batch, and re-checks `status === Active` after acquiring the
 * lock so double-processing — the same reservation appearing twice in a
 * batch, or two command runs overlapping — is always a no-op the second
 * time, never a double decrement.
 */
final class ReleaseExpiredReservations
{
    public function handle(): int
    {
        $expiredIds = InventoryReservation::withoutGlobalScopes()
            ->where('status', ReservationStatus::Active->value)
            ->where('expires_at', '<', now())
            ->pluck('id');

        $released = 0;

        foreach ($expiredIds as $id) {
            $wasReleased = DB::transaction(function () use ($id) {
                $reservation = InventoryReservation::withoutGlobalScopes()
                    ->whereKey($id)
                    ->lockForUpdate()
                    ->first();

                if ($reservation === null || $reservation->status !== ReservationStatus::Active) {
                    return false;
                }

                $level = InventoryLevel::withoutGlobalScopes()
                    ->where('inventory_item_id', $reservation->inventory_item_id)
                    ->where('location_id', $reservation->location_id)
                    ->lockForUpdate()
                    ->first();

                if ($level !== null) {
                    $level->update(['reserved' => max(0, $level->reserved - $reservation->quantity)]);
                }

                $reservation->update([
                    'status' => ReservationStatus::Expired->value,
                    'released_at' => now(),
                ]);

                return true;
            });

            if ($wasReleased) {
                $released++;
            }
        }

        return $released;
    }
}
