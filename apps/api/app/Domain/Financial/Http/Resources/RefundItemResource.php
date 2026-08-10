<?php

namespace App\Domain\Financial\Http\Resources;

use App\Domain\Financial\Models\RefundItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RefundItem
 */
final class RefundItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'return_item_id' => $this->return_item_id,
            'quantity' => $this->quantity,
            'amount' => $this->amount,
        ];
    }
}
