<?php

namespace App\Domain\Financial\Http\Resources;

use App\Domain\Financial\Models\Refund;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Refund
 */
final class RefundResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'payment_id' => $this->payment_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'amount' => $this->amount,
            'shipping_amount' => $this->shipping_amount,
            'adjustment_amount' => $this->adjustment_amount,
            'reason' => $this->reason,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'items' => RefundItemResource::collection($this->whenLoaded('items')),
            'requested_at' => $this->requested_at,
            'processed_at' => $this->processed_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
