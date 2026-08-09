<?php

namespace App\Domain\Inventory\Http\Resources;

use App\Domain\Inventory\Models\InventoryReservation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin-facing view of a reservation against an Order (spec section 16:
 * the Order page must show Reservations alongside Fulfillments and
 * Shipments) — Authentication/Store membership/TenantContext already gate
 * this the same as every other Order-scoped resource.
 *
 * @mixin InventoryReservation
 */
final class InventoryReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'inventory_item_id' => $this->inventory_item_id,
            'location_id' => $this->location_id,
            'quantity' => $this->quantity,
            'status' => $this->status->value,
            'expires_at' => $this->expires_at,
            'released_at' => $this->released_at,
            'consumed_at' => $this->consumed_at,
        ];
    }
}
