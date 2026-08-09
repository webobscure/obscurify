<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Shipping\Models\ShipmentItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ShipmentItem
 */
final class ShipmentItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_item_id' => $this->order_item_id,
            'quantity' => $this->quantity,
        ];
    }
}
