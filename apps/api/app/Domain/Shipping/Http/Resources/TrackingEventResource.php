<?php

namespace App\Domain\Shipping\Http\Resources;

use App\Domain\Shipping\Models\TrackingEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TrackingEvent
 */
final class TrackingEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'description' => $this->description,
            'occurred_at' => $this->occurred_at,
            'location' => $this->location,
        ];
    }
}
