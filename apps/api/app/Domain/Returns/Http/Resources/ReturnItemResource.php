<?php

namespace App\Domain\Returns\Http\Resources;

use App\Domain\Returns\Models\ReturnItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ReturnItem
 */
final class ReturnItemResource extends JsonResource
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
            'reason' => $this->reason->value,
            'condition' => $this->condition?->value,
            'notes' => $this->notes,
            'inspection' => $this->whenLoaded('inspection', fn () => $this->inspection ? new ReturnInspectionResource($this->inspection) : null),
            'disposition' => $this->whenLoaded('disposition', fn () => $this->disposition ? new ReturnDispositionResource($this->disposition) : null),
        ];
    }
}
