<?php

namespace App\Domain\Returns\Http\Resources;

use App\Domain\Returns\Models\ReturnRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnRequest
 */
final class ReturnResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_id' => $this->order_id,
            'customer_id' => $this->customer_id,
            'number' => $this->number,
            'status' => $this->status->value,
            'requested_at' => $this->requested_at,
            'approved_at' => $this->approved_at,
            'received_at' => $this->received_at,
            'closed_at' => $this->closed_at,
            'notes' => $this->notes,
            'items' => ReturnItemResource::collection($this->whenLoaded('items')),
            'events' => ReturnEventResource::collection($this->whenLoaded('events')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
