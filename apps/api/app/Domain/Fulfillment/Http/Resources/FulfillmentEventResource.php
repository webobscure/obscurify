<?php

namespace App\Domain\Fulfillment\Http\Resources;

use App\Domain\Fulfillment\Models\FulfillmentEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FulfillmentEvent
 */
final class FulfillmentEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
        ];
    }
}
